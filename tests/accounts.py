"""Account/OAuth/privacy/funding regression tests; isolated providers and storage only."""
from pathlib import Path
import concurrent.futures, http.cookiejar, json, os, subprocess, tempfile, time, urllib.parse, urllib.request, urllib.error, uuid

ROOT=Path(__file__).resolve().parents[1]
BASE='http://127.0.0.1:8494'
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,*args,**kwargs): return None
class Client:
    def __init__(self):
        self.jar=http.cookiejar.CookieJar()
        self.http=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar),NoRedirect())
        self.csrf=''
    def request(self,path,body=None,key=None,extra=None):
        headers={'Content-Type':'application/json','X-CSRF-Token':self.csrf,**(extra or {})}
        if key is not None:headers['X-User-OpenRouter-Key']=key
        req=urllib.request.Request(BASE+path,data=None if body is None else json.dumps(body).encode(),headers=headers)
        try:r=self.http.open(req)
        except urllib.error.HTTPError as e:r=e
        raw=r.read();return r.status, json.loads(raw) if 'application/json' in r.headers.get('Content-Type','') else raw.decode(),r.headers
    def init(self):
        status,data,_=self.request('/api.php?action=init_data');assert status==200,data;self.csrf=data['csrf_token'];return data
    def post(self,action,key=None,**payload):return self.request('/api.php',dict(action=action,**payload),key)[:2]
    def begin(self):
        status,_,headers=self.request('/auth.php?action=login');assert status==302
        assert 'SameSite=Lax' in headers.get('Set-Cookie','') and 'HttpOnly' in headers.get('Set-Cookie','')
        params=urllib.parse.parse_qs(urllib.parse.urlparse(headers['Location']).query)
        assert params['scope']==['openid profile'] and params['redirect_uri']==[BASE+'/auth.php?action=callback']
        return params['state'][0]
    def login(self,alias='alice'):
        state=self.begin();status,_,headers=self.request('/auth.php?action=callback&'+urllib.parse.urlencode({'state':state,'code':alias}))
        assert status==303,(status,headers);return self.init()
    def start(self,key=None,request_id=None,mode='human'):
        return self.post('start_session',key,request_id=request_id or uuid.uuid4().hex,model='google/gemini-2.5-flash-lite',
            interrogator_type=mode,interrogator_model='mistralai/mistral-small-3.2-24b-instruct',case_file='char_1_victor_vance.md')
    def grade(self,s,key=None):
        action='ai_vs_ai_step' if s['interrogator_type']=='ai' else 'send_message'
        status,d=self.post(action,key,session_id=s['session_id'],request_id=uuid.uuid4().hex,expected_turns=0,message='ما تفسير التناقض في تسلسل الوقائع؟');assert status==200,d
        status,d=self.post('evaluate_session',key,session_id=s['session_id']);assert status==200 and d['session']['evaluation_status']=='complete',d
        return d['session']

def run(storage):
    a,b,guest=Client(),Client(),Client();anonymous=guest.init();assert anonymous['participant_profile'] is None
    assert guest.start()[0]==401
    assert guest.post('key_balance','sk-or-test')[0]==401
    state=a.begin();assert a.request('/auth.php?action=callback&state=wrong&code=alice')[0]==400
    assert a.request('/auth.php?action=callback&state='+state+'&code=alice')[0]==400,'State must be single use'
    profile=a.login()['participant_profile'];assert profile['display_name']=='Test Operator <safe>'
    assert 'email' not in json.dumps(profile)
    assert a.init()['quota']['remaining']==3
    b.login('bob');assert b.init()['participant_profile']['public_id']!=profile['public_id']
    rid=uuid.uuid4().hex;status,d=a.start(request_id=rid);assert status==200,d;s=d['session'];sid=s['session_id']
    assert s['funding_source']=='site' and 'key_fingerprint' not in s and 'owner_id' not in s
    assert a.start(request_id=rid)[1]['session']['session_id']==sid
    assert a.init()['quota']['used']==1
    assert b.request('/api.php?action=load_session&session_id='+sid)[0]==404
    assert guest.request('/api.php?action=load_session&session_id='+sid)[0]==404
    assert b.post('delete_session',session_id=sid,confirm=sid)[0]==403
    assert guest.request('/api.php?action=public_profile&profile_id='+profile['public_id'])[0]==404
    recovered=Client();recovered.login('alice');assert recovered.request('/api.php?action=load_session&session_id='+sid)[0]==200
    assert len(recovered.init()['logs'])==1,'New browser must recover only the same LinkedIn account'
    s=a.grade(s);assert s['rankings']['prisoner']['provisional'] and s['rankings']['prisoner']['rank']==1
    assert s['cost']['funding_source']=='site' and s['cost']['total_usd']>0
    assert a.post('submit_review',session_id=sid,role='prisoner',rating=2,text='PRIVATE_REVIEW_SENTINEL')[0]==200
    assert 'PRIVATE_REVIEW_SENTINEL' not in json.dumps(guest.init())
    assert guest.request('/api.php?action=leaderboard')[1]['total']==0
    assert guest.request('/public.php?session_id='+sid)[0]==404
    assert a.post('publish_session',session_id=sid)[0]==200
    pub=guest.request('/api.php?action=load_session&public=1&session_id='+sid)[1]['session']
    assert pub['read_only'] and not pub['can_delete'] and pub['operator_profile']['public_id']==profile['public_id']
    assert not pub['rankings']['prisoner']['provisional']
    public_meta=guest.request('/public.php?session_id='+sid);assert public_meta[0]==200 and 'og:title' in public_meta[1]
    assert guest.request('/api.php?action=leaderboard&role=prisoner')[1]['total']==1
    public_profile=guest.request('/api.php?action=public_profile&profile_id='+profile['public_id'])[1]
    assert len(public_profile['logs'])==1 and public_profile['logs'][0]['user_reviews']['prisoner']['text']=='PRIVATE_REVIEW_SENTINEL'
    assert a.start()[0]==200 and a.start()[0]==200
    assert a.start()[0]==400 and a.init()['quota']['remaining']==0
    assert a.post('delete_session',session_id=sid,confirm='wrong')[0]==400
    assert a.post('delete_session',session_id=sid,confirm=sid)[0]==200
    assert not (storage/(sid+'.json')).exists()
    assert sid not in (storage/'benchmark.json').read_text()
    assert guest.request('/api.php?action=load_session&session_id='+sid)[0]==404
    assert guest.request('/public.php?session_id='+sid)[0]==404
    assert guest.request('/api.php?action=leaderboard')[1]['total']==0
    assert a.start()[0]==400,'Deletion must not restore the daily allowance'
    assert a.start(key='invalid-key')[0]==400
    balance=a.post('key_balance','sk-or-test-personal')[1]['balance']
    assert balance['balance']==7.5 and balance['key_remaining']==2.5 and balance['available_to_spend']==2.5,'Key budget must precede account credit'
    status,d=a.start(key='sk-or-test-personal',mode='ai');assert status==200,d;personal=d['session'];pid=personal['session_id']
    assert personal['funding_source']=='personal' and a.init()['quota']['remaining']==0
    for _ in range(4):assert a.start(key='sk-or-test-personal')[0]==200,'Personal key must bypass daily site quota'
    assert a.start()[0]==400,'Removing the key must restore site quota'
    assert a.post('ai_vs_ai_step',session_id=pid,request_id=uuid.uuid4().hex,expected_turns=0)[0]==400,'No site-key fallback'
    assert a.post('ai_vs_ai_step','sk-or-other',session_id=pid,request_id=uuid.uuid4().hex,expected_turns=0)[0]==400
    personal=a.grade(personal,'sk-or-test-personal')
    assert personal['cost']['funding_source']=='personal' and 'key_fingerprint' not in personal
    assert a.post('publish_session',session_id=pid)[0]==200
    assert guest.request('/api.php?action=leaderboard')[1]['total']==2
    assert guest.request('/api.php?action=leaderboard&role=prisoner&model=mistralai%2Fmistral-small-3.2-24b-instruct')[1]['total']==0
    assert guest.request('/api.php?action=leaderboard&role=interrogator')[1]['total']==1
    assert a.post('key_balance','sk-or-test')[1]['balance']['balance']==7.5
    uncapped=a.post('key_balance','sk-or-uncapped')[1]['balance'];assert uncapped['balance'] is None and uncapped['key_remaining'] is None
    assert a.post('logout')[0]==200;assert a.init()['participant_profile'] is None and a.start()[0]==401
    a.login('alice');assert a.init()['quota']['remaining']==0
    assert len(a.init()['logs'])==7
    # A quota bucket rollover is independent of PHP/server timezone.
    account=next(a for a in (json.loads(f.read_text()) for f in (storage/'accounts').glob('*.json')) if a['public_id']==profile['public_id'])
    quota_path=storage/('quota_'+account['id']+'.json')
    q=json.loads(quota_path.read_text());q['day']='2000-01-01';quota_path.write_text(json.dumps(q))
    assert a.init()['quota']['remaining']==3
    assert a.start()[0]==200
    # Eight simultaneous processes exercise the file lock (PHP's dev server is single-threaded).
    race=storage/'race';race.mkdir();jobs=[]
    for i in range(8):jobs.append(subprocess.Popen(['php',str(ROOT/'tests/quota_driver.php'),str(race),str(i)],stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True))
    results=[p.communicate(timeout=30)[0].strip() for p in jobs];assert results.count('created')==3,results
    assert all(x in ['created','limited'] for x in results),results
    print('PASS accounts: OAuth state/replay, account recovery/isolation, private review protection, published-only profiles/leaderboards, cost/funding binding, 3/day quota/race/rollover, BYOK balance, logout, complete conversation deletion.')

if __name__=='__main__':
    with tempfile.TemporaryDirectory(prefix='prisonlab-accounts-') as tmp:
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
                print((storage/'server.log').read_text(encoding='utf-8',errors='replace')[-5000:]);raise
            finally:p.terminate();p.wait(timeout=10)
