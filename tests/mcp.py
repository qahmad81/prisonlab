"""Role-scoped MCP interoperability/lifecycle regressions, isolated mock providers only."""
from pathlib import Path
import http.cookiejar,json,os,subprocess,tempfile,time,urllib.request,urllib.error,urllib.parse,uuid
ROOT=Path(__file__).resolve().parents[1]
BASE='http://127.0.0.1:8494'
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,*args,**kwargs):return None
class Client:
    def __init__(self):
        self.http=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()),NoRedirect());self.csrf=''
    def request(self,path,body=None,key=None):
        req=urllib.request.Request(BASE+path,data=None if body is None else json.dumps(body).encode(),headers={'Content-Type':'application/json','X-CSRF-Token':self.csrf,**({'X-User-OpenRouter-Key':key} if key else {})})
        try:r=self.http.open(req)
        except urllib.error.HTTPError as e:r=e
        raw=r.read();return r.status,json.loads(raw) if 'application/json' in r.headers.get('Content-Type','') else raw.decode(),r.headers
    def login(self,alias='alice'):
        self.csrf=self.request('/api.php?action=init_data')[1]['csrf_token']
        if (ROOT/'lib/accounts.php').exists():
            _,_,h=self.request('/auth.php?action=login');state=urllib.parse.parse_qs(urllib.parse.urlparse(h['Location']).query)['state'][0]
            assert self.request('/auth.php?action=callback&'+urllib.parse.urlencode({'state':state,'code':alias}))[0]==303
            self.csrf=self.request('/api.php?action=init_data')[1]['csrf_token']
    def post(self,action,key=None,**args):return self.request('/api.php',dict(action=action,**args),key)[:2]

def rpc(gate,method,params=None,origin=None):
    body={'jsonrpc':'2.0','id':1,'method':method,'params':params or {}}
    headers={'Content-Type':'application/json','Accept':'application/json, text/event-stream'}
    if origin:headers['Origin']=origin
    req=urllib.request.Request(BASE+'/mcp.php?gate='+urllib.parse.quote(gate),data=json.dumps(body).encode(),headers=headers)
    try:r=urllib.request.urlopen(req,timeout=22)
    except urllib.error.HTTPError as e:r=e
    return r.status,json.load(r)

def run(storage):
    owner,other=Client(),Client();owner.login();other.login('bob')
    def start(mode,actors):
        code,d=owner.post('start_session','sk-or-test-personal',request_id=uuid.uuid4().hex,model='google/gemini-2.5-flash-lite',interrogator_type=mode,
            interrogator_model='mistralai/mistral-small-3.2-24b-instruct',case_file='char_1_victor_vance.md',mcp_actors=actors)
        assert code==200,d
        return d['session']
    def gate(s,role):
        code,d=owner.post('mcp_gate',session_id=s['session_id'],role=role);assert code==200,d
        return urllib.parse.parse_qs(urllib.parse.urlparse(d['url']).query)['gate'][0]
    def step(s,turn=0,message='ما تفسير التناقض؟',rid=None):
        return owner.post({'ai':'ai_vs_ai_step','human':'send_message','reverse':'reverse_answer'}[s['interrogator_type']],
            'sk-or-test-personal',session_id=s['session_id'],expected_turns=turn,request_id=rid or uuid.uuid4().hex,message=message)
    actors={r:{'name':'Local Agent '+r,'effort':'high'} for r in ['prisoner','interrogator']}
    s=start('ai',actors);sid=s['session_id'];tokens={r:gate(s,r) for r in actors}
    assert s['prisoner_reasoning']['self_reported'] and s['model'].startswith('mcp/')
    assert other.post('mcp_gate',session_id=sid,role='prisoner')[0]==403
    assert rpc('bad','initialize')[0]==401
    assert rpc(tokens['prisoner'],'initialize',origin='https://evil.example')[0]==403
    init=rpc(tokens['prisoner'],'initialize',{'protocolVersion':'2025-11-25'})[1]['result'];assert init['protocolVersion']=='2025-11-25'
    tools=rpc(tokens['prisoner'],'tools/list')[1]['result']['tools'];assert {x['name'] for x in tools}=={'prisonlab_wait','prisonlab_reply'}
    assert 'prisoner' not in json.dumps(init['capabilities'])
    before=time.monotonic();waited=rpc(tokens['prisoner'],'tools/call',{'name':'prisonlab_wait','arguments':{'wait_seconds':1}})
    assert time.monotonic()-before>=.95 and 'waiting' in waited[1]['result']['content'][0]['text']
    bridge=subprocess.run(['python',str(ROOT/'tools/mcp_stdio.py')],input=json.dumps({'jsonrpc':'2.0','id':3,'method':'initialize','params':{'protocolVersion':'2025-11-25'}})+'\n'+json.dumps({'jsonrpc':'2.0','method':'notifications/initialized'})+'\n'+json.dumps({'jsonrpc':'2.0','id':4,'method':'tools/list'})+'\n',text=True,encoding='utf-8',capture_output=True,env={**os.environ,'PRISONLAB_MCP_URL':BASE+'/mcp.php?gate='+tokens['prisoner']},timeout=10)
    bridge_responses=[json.loads(line) for line in bridge.stdout.splitlines()];assert [r['id'] for r in bridge_responses]==[3,4] and bridge.returncode==0
    bearer=urllib.request.Request(BASE+'/mcp.php',data=json.dumps({'jsonrpc':'2.0','id':1,'method':'ping'}).encode(),headers={'Content-Type':'application/json','Authorization':'Bearer '+tokens['prisoner']})
    assert urllib.request.urlopen(bearer).status==200
    rid=uuid.uuid4().hex
    code,d=step(s,rid=rid);assert code==200 and d['mcp_wait'],d
    def task(role):
        code,d=rpc(tokens[role],'tools/call',{'name':'prisonlab_wait','arguments':{'wait_seconds':0}});assert code==200 and not d['result']['isError'],d
        return json.loads(d['result']['content'][0]['text'])
    def reply(role,t,output):
        return rpc(tokens[role],'tools/call',{'name':'prisonlab_reply','arguments':{'task_id':t['task']['id'],'output':output}})[1]['result']
    t=task('interrogator');assert t['status']=='task' and t['task']['bootstrap']
    assert 'ملف القضية الكامل' in json.dumps(t,ensure_ascii=False)
    assert task('prisoner')['status']=='waiting'
    output={'question':'كيف تفسر وصول الملفات إلى حسابك؟','expression':'serious','inner_thought':'PRIVATE_SENTINEL'}
    assert not reply('interrogator',t,output)['isError']
    assert not reply('interrogator',t,output)['isError'],'Identical reply replay is idempotent'
    assert reply('interrogator',t,{'question':'رد مختلف'})['isError']
    assert reply('prisoner',t,{'reply':'تعدي الدور'})['isError']
    code,d=step(s,rid=rid);assert code==200 and d['mcp_wait'];s=d['session']
    assert len(s['messages'])==0 and s['pending_question']==output['question']
    pt=task('prisoner');assert pt['task']['bootstrap'] and 'PRIVATE_SENTINEL' not in json.dumps(pt)
    assert 'حالتك الداخلية الملزمة' in json.dumps(pt,ensure_ascii=False)
    count=len(json.loads((storage/'test-calls.json').read_text()))
    for _ in range(2):assert step(s,rid=rid)[1]['mcp_wait']
    assert len(json.loads((storage/'test-calls.json').read_text()))==count,'Waiting must not rebuy JEV'
    assert not reply('prisoner',pt,{'reply':'وجود الملف في حسابي لا يثبت أنني أرسلته؛ وضّح وقت الوصول.','state_id':'calm','reasoning':'NEVER_STORE_RAW'})['isError']
    code,d=step(s,rid=rid);assert code==200 and not d.get('mcp_wait'),d;s=d['session'];assert s['turns_count']==1
    assert step(s,0,rid=rid)[1]['duplicate'],'Same browser request cannot append twice'
    assert 'NEVER_STORE_RAW' not in (storage/(sid+'.json')).read_text()
    rid2=uuid.uuid4().hex;assert step(s,1,rid=rid2)[1]['mcp_wait']
    it2=task('interrogator');assert not it2['task']['bootstrap'];encoded=json.dumps(it2,ensure_ascii=False)
    assert s['messages'][-1]['content'] in encoded and 'ملف القضية الكامل' not in encoded and 'PRIVATE_SENTINEL' not in encoded
    old=tokens['interrogator'];tokens['interrogator']=gate(s,'interrogator');assert rpc(old,'ping')[0]==401
    assert task('interrogator')['task']['id']==it2['task']['id'],'Rotation preserves the pending task'
    assert not reply('interrogator',it2,{'question':'من كان يملك صلاحية الحساب في ذلك الوقت؟'})['isError']
    assert step(s,1,rid=rid2)[1]['mcp_wait'];pt2=task('prisoner');assert not pt2['task']['bootstrap']
    # Overlong speech remains measured, not silently truncated; corrective task keeps successful stages.
    assert not reply('prisoner',pt2,{'reply':'كلمة '*180})['isError']
    code,d=step(s,1,rid=rid2);assert code==503 and d['retryable'],d
    assert step(s,1,rid=rid2)[1]['mcp_wait'];correction=task('prisoner');assert correction['task']['id']!=pt2['task']['id']
    assert 'المحاولة السابقة تجاوزت' in json.dumps(correction,ensure_ascii=False)
    assert not reply('prisoner',correction,{'reply':'الصلاحيات مشتركة وفق إجراء العمل؛ لا أعرف من دخل حينها.'})['isError']
    code,d=step(s,1,rid=rid2);assert code==200,d;s=d['session'];assert s['turns_count']==2 and s['output_events']
    assert owner.post('compact_now','sk-or-test-personal',session_id=sid)[0]==200
    calls=json.loads((storage/'test-calls.json').read_text());assert not any(c['model'].startswith('mcp/') for c in calls)
    assert owner.post('evaluate_session','sk-or-test-personal',session_id=sid)[1]['session']['evaluation_status']=='complete'
    assert task('prisoner')['status']=='ended'
    assert owner.post('publish_session',session_id=sid)[0]==200
    assert rpc(tokens['prisoner'],'ping')[0]==401,'Publication disables every gate'
    pub=other.request('/api.php?action=load_session&session_id='+sid)[1]['session']
    assert 'mcp_status' not in pub and tokens['prisoner'].split('.')[-1] not in json.dumps(pub)
    assert owner.post('delete_session',session_id=sid,confirm=sid)[0]==200
    assert rpc(tokens['prisoner'],'ping')[0]==401 and not list((storage/'mcp').glob(sid+'*.json'))
    # Human investigator / MCP prisoner.
    s=start('human',{'prisoner':actors['prisoner']});tokens={'prisoner':gate(s,'prisoner')};rid=uuid.uuid4().hex
    assert owner.post('mcp_gate',session_id=s['session_id'],role='prisoner',revoke=True)[0]==200
    assert rpc(tokens['prisoner'],'ping')[0]==401
    tokens['prisoner']=gate(s,'prisoner')
    assert step(s,rid=rid)[1]['mcp_wait'];t=task('prisoner');reply('prisoner',t,{'reply':'لم أنقل الملفات بنفسي.'})
    assert step(s,rid=rid)[1]['session']['turns_count']==1
    # MCP investigator / human accused retains canonical user/assistant schema.
    s=start('reverse',{'interrogator':actors['interrogator']});tokens={'interrogator':gate(s,'interrogator')}
    def question():return owner.post('reverse_question','sk-or-test-personal',session_id=s['session_id'])
    assert question()[1]['mcp_wait'];t=task('interrogator');reply('interrogator',t,{'question':'كيف وصلت الملفات إلى حسابك؟'})
    code,d=question();assert code==200 and d['session']['awaiting_question']['sense'],d
    code,d=step(s,message='لدي وصول وظيفي مشروع.');assert code==200,d
    assert [m['role'] for m in d['session']['messages']]==['user','assistant']
    # Mixed OpenRouter investigator with MCP prisoner.
    s=start('ai',{'prisoner':actors['prisoner']});tokens={'prisoner':gate(s,'prisoner')};rid=uuid.uuid4().hex
    assert step(s,rid=rid)[1]['mcp_wait'];t=task('prisoner');reply('prisoner',t,{'reply':'هذا الوصف لا يثبت التهمة.'})
    assert step(s,rid=rid)[1]['session']['turns_count']==1
    # Free OpenRouter investigator paired with MCP prisoner keeps support on the original key.
    import hashlib
    catalog=owner.request('/api.php?action=init_data')[1]['models']
    free=next(m['id'] for m in catalog if not m.get('catalog_unavailable') and float(m['pricing']['prompt_per_token'])==0 and float(m['pricing']['completion_per_token'])==0)
    code,d=owner.post('start_session','sk-or-test-personal',request_id=uuid.uuid4().hex,model='google/gemini-2.5-flash-lite',interrogator_type='ai',interrogator_model=free,case_file='char_1_victor_vance.md',mcp_actors={'prisoner':actors['prisoner']});assert code==200,d
    s=d['session'];tokens={'prisoner':gate(s,'prisoner')};rid=uuid.uuid4().hex
    before=len(json.loads((storage/'test-calls.json').read_text()))
    assert step(s,rid=rid)[1]['mcp_wait'];t=task('prisoner');reply('prisoner',t,{'reply':'هذا الوصف لا يثبت التهمة.'})
    assert step(s,rid=rid)[1]['session']['turns_count']==1
    calls=json.loads((storage/'test-calls.json').read_text())[before:]
    assert any(c['model']==free and c['test_key_hash']==hashlib.sha256(b'free-test-key').hexdigest() and c['price_caps']=={'prompt':0,'completion':0} for c in calls)
    assert all(c['test_key_hash']==hashlib.sha256(b'sk-or-test-personal').hexdigest() for c in calls if c['decisions'])
    print('PASS MCP: HTTP initialize/tools, role isolation, rotation, deletion, delta context, spectator isolation, idempotent stages/replies, output correction, both external actors, mixed provider and both human modes, OpenRouter-only auxiliary funding.')

if __name__=='__main__':
    with tempfile.TemporaryDirectory(prefix='prisonlab-mcp-') as tmp:
        storage=Path(tmp);env={**os.environ,'LAB_DATA_DIR':tmp,'LAB_TEST_LEGACY_IDENTITY':'0'}
        with (storage/'server.log').open('w') as log:
            p=subprocess.Popen(['php','-d','auto_prepend_file='+str(ROOT/'tests/mock-provider.php'),'-S','127.0.0.1:8494','router.php'],cwd=ROOT,env=env,stdout=log,stderr=log,creationflags=subprocess.CREATE_NO_WINDOW if os.name=='nt' else 0)
            try:
                for _ in range(70):
                    try:
                        if urllib.request.urlopen(BASE+'/api.php?action=init_data').status==200:break
                    except OSError:time.sleep(.1)
                run(storage)
            except Exception:
                print((storage/'server.log').read_text(encoding='utf-8',errors='replace')[-4000:]);raise
            finally:p.terminate();p.wait(timeout=10)
