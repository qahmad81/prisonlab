"""Isolated free/paid sponsor routing and quota regression; synthetic keys only."""
from pathlib import Path
import hashlib,json,os,subprocess,tempfile,time,urllib.request,uuid
from accounts import Client,ROOT,BASE
FREE_ID='sp_111111111111111111111111'
def digest(x):return hashlib.sha256(x.encode()).hexdigest()
def run(storage):
    owner,donor,free_owner=Client(),Client(),Client()
    owner.login('consumer');donor.login('donor');free_owner.login('sponsor-b')
    data=owner.init();models=data['models']
    free=next(m['id'] for m in models if not m.get('catalog_unavailable') and float(m['pricing']['prompt_per_token'])==0 and float(m['pricing']['completion_per_token'])==0)
    paid='google/gemini-2.5-flash-lite'
    status,d,_=donor.request('/api.php',dict(action='save_sponsor',label='Paid fixture',key='paid-fixture-key',daily_limit=20,price_cap=1,active=True));assert status==200,d
    primary=d['sponsor'];free_record=next(x for x in free_owner.init()['sponsors'] if x['id']==FREE_ID)
    def start(mode,model,im='',rid=None,key=None,actors=None):
        return owner.post('start_session',key,request_id=rid or uuid.uuid4().hex,sponsor_id=primary['id'],model=model,interrogator_type=mode,interrogator_model=im,case_file='char_1_victor_vance.md',mcp_actors=actors or {})
    def used(sid):return next(x['used'] for x in owner.init()['sponsors'] if x['id']==sid)
    rid=uuid.uuid4().hex;p0,f0=used(primary['id']),used(FREE_ID)
    status,d=start('ai',free,paid,rid);assert status==200,d;s=d['session'];assert s['free_funding_mode']=='mixed' and s['sponsor_id']==primary['id'] and s['free_sponsor']['id']==FREE_ID
    assert (used(primary['id']),used(FREE_ID))==(p0+1,f0+1)
    status,replay=start('ai',free,paid,rid);assert status==200 and replay['session']['session_id']==s['session_id'];assert (used(primary['id']),used(FREE_ID))==(p0+1,f0+1)
    before=len(json.loads((storage/'test-calls.json').read_text())) if (storage/'test-calls.json').exists() else 0
    status,d=owner.post('ai_vs_ai_step',session_id=s['session_id'],request_id=uuid.uuid4().hex,expected_turns=0);assert status==200,d
    calls=json.loads((storage/'test-calls.json').read_text())[before:]
    assert any(x['model']==free and x['test_key_hash']==digest('free-test-key') and x['price_caps']=={'prompt':0,'completion':0} for x in calls)
    assert all(x['test_key_hash']==digest('paid-fixture-key') for x in calls if x['decisions'] or x['model']==paid)
    cost=d['session']['cost'];assert cost['free_sponsor']['id']==FREE_ID and cost['by_funding_part']['free_actor']['recorded_calls']==1 and cost['by_funding_part']['primary']['recorded_calls']>=1
    assert all(secret not in json.dumps(d) for secret in ['free-test-key','paid-fixture-key','fingerprint',digest('free-test-key')])
    # Full free mode bypasses personal/selected primary and binds the free sponsor to all stages.
    for mode,model,im in [('human',free,''),('reverse','',free),('ai',free,free)]:
        before=len(json.loads((storage/'test-calls.json').read_text()));p0,f0=used(primary['id']),used(FREE_ID)
        status,d=start(mode,model,im,key='personal-fixture-key');assert status==200,d;trial=d['session'];assert trial['sponsor_id']==FREE_ID and trial['free_funding_mode']=='full'
        assert (used(primary['id']),used(FREE_ID))==(p0,f0+1)
        if mode=='reverse':status,d=owner.post('reverse_question',session_id=trial['session_id'],request_id=uuid.uuid4().hex,expected_turns=0)
        else:status,d=owner.post('send_message' if mode=='human' else 'ai_vs_ai_step',session_id=trial['session_id'],request_id=uuid.uuid4().hex,expected_turns=0,message='ما تفسير الواقعة؟')
        assert status==200,d
        if mode!='reverse':status,d=owner.post('evaluate_session',session_id=trial['session_id']);assert status==200,d
        calls=json.loads((storage/'test-calls.json').read_text())[before:];assert calls and all(x['test_key_hash']==digest('free-test-key') for x in calls)
        assert all(x['price_caps']=={'prompt':0,'completion':0} for x in calls if x['model']==free and not x['decisions'])
        if mode!='reverse':assert any(x['model']!=free and not x['decisions'] and x['price_caps'] and x['price_caps']['completion']>0 for x in calls)
    # A failed secondary reservation must not consume the primary donor quota.
    limit=used(FREE_ID);assert limit>0
    status,d=free_owner.post('save_sponsor',id=FREE_ID,revision=free_record['revision'],label=free_record['label'],daily_limit=limit,price_cap=0,active=True);assert status==200,d;free_record=d['sponsor']
    p0=used(primary['id']);assert start('ai',free,paid)[0]==400;assert used(primary['id'])==p0
    # One sponsor serving both roles is reserved once, not twice.
    status,d=free_owner.post('save_sponsor',id=FREE_ID,revision=free_record['revision'],label=free_record['label'],daily_limit=1000,price_cap=1,active=True);assert status==200,d;free_record=d['sponsor']
    f0=used(FREE_ID);status,d=owner.post('start_session',request_id=uuid.uuid4().hex,sponsor_id=FREE_ID,model=free,interrogator_type='ai',interrogator_model=paid,case_file='char_1_victor_vance.md');assert status==200,d
    assert used(FREE_ID)==f0+1 and d['session']['sponsor_id']==FREE_ID
    # Revocation blocks without fallback; rotation keeps the same sponsor and quota.
    status,d=free_owner.post('save_sponsor',id=FREE_ID,revision=free_record['revision'],label=free_record['label'],daily_limit=1000,price_cap=0,active=False);assert status==200,d;free_record=d['sponsor']
    assert not owner.init()['env']['free_models_available']
    count=len(json.loads((storage/'test-calls.json').read_text()));assert owner.post('ai_vs_ai_step',session_id=s['session_id'],request_id=uuid.uuid4().hex,expected_turns=1)[0]==400
    assert len(json.loads((storage/'test-calls.json').read_text()))==count
    assert start('human',free)[0]==400
    status,d,_=free_owner.request('/api.php',dict(action='save_sponsor',id=FREE_ID,revision=free_record['revision'],label=free_record['label'],key='free-rotated-key',daily_limit=1000,price_cap=0,active=True));assert status==200,d
    status,d=owner.post('ai_vs_ai_step',session_id=s['session_id'],request_id=uuid.uuid4().hex,expected_turns=1);assert status==200,d
    calls=json.loads((storage/'test-calls.json').read_text())[count:];assert any(x['model']==free and x['test_key_hash']==digest('free-rotated-key') for x in calls)
    # The primary donor is also binding in mixed mode; disabling it cannot charge the free donor instead.
    status,d=donor.post('save_sponsor',id=primary['id'],revision=primary['revision'],label=primary['label'],daily_limit=20,price_cap=1,active=False);assert status==200,d
    count=len(json.loads((storage/'test-calls.json').read_text()));assert owner.post('ai_vs_ai_step',session_id=s['session_id'],request_id=uuid.uuid4().hex,expected_turns=2)[0]==400
    assert len(json.loads((storage/'test-calls.json').read_text()))==count
    print('PASS free routing: mixed and complete funding, quotas, idempotence, cost attribution, revocation, rotation and secret isolation.')
if __name__=='__main__':
    with tempfile.TemporaryDirectory(prefix='prisonlab-free-routing-') as tmp:
        storage=Path(tmp);subprocess.run(['php',str(ROOT/'tests/free_sponsor_fixture.php'),tmp],check=True)
        with (storage/'server.log').open('w') as log:
            p=subprocess.Popen(['php','-d','auto_prepend_file='+str(ROOT/'tests/mock-provider.php'),'-S','127.0.0.1:8494','router.php'],cwd=ROOT,env={**os.environ,'LAB_DATA_DIR':tmp,'LAB_TEST_LEGACY_IDENTITY':'0'},stdout=log,stderr=log,creationflags=subprocess.CREATE_NO_WINDOW if os.name=='nt' else 0)
            try:
                for _ in range(70):
                    try:
                        if urllib.request.urlopen(BASE+'/api.php?action=init_data').status==200:break
                    except OSError:time.sleep(.1)
                run(storage)
            finally:p.terminate();p.wait(timeout=10)
