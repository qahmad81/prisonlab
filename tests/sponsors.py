"""Private key custody, donor attribution and concurrent per-donor quotas; no paid calls."""
from pathlib import Path
import hashlib,json,os,subprocess,tempfile,time,urllib.request,uuid
from accounts import Client,ROOT,BASE

def run(storage):
    a,b,c=Client(),Client(),Client();a.init();b.init();c.init()
    assert c.post('save_sponsor',label='Denied',key='sk-or-never',daily_limit=2,price_cap=3,active=True)[0]==401
    pa=a.login('sponsor-a')['participant_profile'];pb=b.login('sponsor-b')['participant_profile'];c.login('consumer')
    def save(client,key,label,limit,price,prior=None,active=True):
        body=dict(label=label,daily_limit=limit,price_cap=price,active=active)
        if key is not None:body['key']=key
        if prior:body.update(id=prior['id'],revision=prior['revision'])
        status,d,_=client.request('/api.php',dict(action='save_sponsor',**body));assert status==200,d;return d['sponsor']
    sa=save(a,'sk-or-sponsor-a','Supporter A <safe>',2,3)
    fixture=next(x for x in b.init()['sponsors'] if x['id']=='sp_111111111111111111111111')
    sb=save(b,'sk-or-sponsor-b','Supporter B',1,0,fixture)
    raw=(storage/'sponsor-vault.json').read_text();assert 'sk-or-sponsor-' not in raw and 'secret' in raw
    assert len((storage/'sponsor-vault.key').read_bytes())==32
    status,d,_=c.request('/api.php?action=sponsors');assert status==200
    public=json.dumps(d);assert all(x not in public for x in ['secret','fingerprint','owner_id','sk-or-sponsor'])
    assert all(not r['is_owner'] for r in d['sponsors'])
    assert a.post('save_sponsor',id=sb['id'],revision=sb['revision'],label='Steal',daily_limit=1,price_cap=50,active=True)[0]==400
    assert a.post('delete_sponsor',id=sb['id'],confirm=sb['id'])[0]==400
    models=c.init()['models'];priced=next(m['id'] for m in models if 1<max(float(m['pricing']['completion_per_1m']),float(m['pricing']['prompt_per_1m']))<=3)
    free=next(m['id'] for m in models if float(m['pricing']['completion_per_1m'])==0 and float(m['pricing']['prompt_per_1m'])==0)
    def start(client,sponsor,model,key=None,rid=None):return client.post('start_session',key,sponsor_id=sponsor,request_id=rid or uuid.uuid4().hex,model=model,interrogator_type='human',case_file='char_1_victor_vance.md')
    rid=uuid.uuid4().hex;status,d=start(c,sa['id'],priced,rid=rid);assert status==200,d;paid=d['session']
    assert paid['funding_source']=='sponsor' and paid['sponsor_profile']==pa and paid['cost']['sponsor_label']==sa['label']
    assert start(c,sa['id'],priced)[0]==200 and start(c,sa['id'],priced)[0]==400
    assert start(c,sa['id'],priced,rid=rid)[1]['session']['session_id']==paid['session_id']
    assert c.init()['quota']['remaining']==3,'Donor quota consumed the site quota'
    assert start(c,sb['id'],priced,key='sk-or-personal-does-not-bypass')[0]==400
    status,d=start(c,sb['id'],free);assert status==200,d;zero=d['session']
    before=len(json.loads((storage/'test-calls.json').read_text())) if (storage/'test-calls.json').exists() else 0
    for turn in range(4):
        status,d=c.post('send_message',session_id=zero['session_id'],request_id=uuid.uuid4().hex,expected_turns=turn,message='ما تفسير هذه الواقعة؟');assert status==200,d
    status,d=c.post('compact_now',session_id=zero['session_id']);assert status==200,d
    assert any(u['purpose']=='compaction' for u in d['session']['usage'])
    status,d=c.post('evaluate_session',session_id=zero['session_id']);assert status==200,d
    graded=d['session'];calls=json.loads((storage/'test-calls.json').read_text())[before:]
    assert graded['evaluation_status']=='complete'
    assert all(x['test_key_hash']==hashlib.sha256(b'sk-or-sponsor-b').hexdigest() for x in calls),'Actor or support funding changed'
    actor=next(x for x in calls if not x['decisions'] and x['model']==free);assert actor['price_caps']==dict(prompt=0,completion=0)
    assert any('confession' in x['question_ids'] for x in calls) and any(x['question_ids']==[] and x['model']!=free for x in calls)
    assert c.post('publish_session',session_id=zero['session_id'])[0]==200
    anon=Client();assert anon.request('/api.php?action=load_session&public=1&session_id='+zero['session_id'])[1]['session']['cost']['sponsor_profile']==pb
    assert anon.request('/api.php?action=load_session&session_id='+paid['session_id'])[0]==404
    assert a.request('/api.php?action=load_session&session_id='+paid['session_id'])[0]==404,'Sponsor saw a funded private transcript'
    # Ownership, immutable quotas and source binding survive rotation and price changes.
    sb=save(b,'sk-or-sponsor-b-rotated',sb['label'],1,0,sb)
    assert start(c,sb['id'],free)[0]==400,'Rotation reset quota'
    sa=save(a,None,sa['label'],2,0,sa)
    assert c.post('send_message',session_id=paid['session_id'],request_id=uuid.uuid4().hex,expected_turns=0,message='Question')[0]==400,'Lower price cap not enforced on resume'
    sa=save(a,None,sa['label'],2,3,sa,active=False)
    assert c.post('send_message',session_id=paid['session_id'],request_id=uuid.uuid4().hex,expected_turns=0,message='Question')[0]==400,'Disabled sponsor fell back to site funding'
    sa=save(a,None,sa['label'],2,3,sa)
    assert start(c,sa['id'],priced)[0]==400,'Editing limits reset quota'
    status,d=start(a,sb['id'],free);assert status==200,d;pending=d['session']
    assert b.post('delete_sponsor',id=sb['id'],confirm=sb['id'])[0]==200
    assert a.post('send_message',session_id=pending['session_id'],request_id=uuid.uuid4().hex,expected_turns=0,message='Question')[0]==400,'Deleted sponsor fell back to site key'
    assert anon.request('/api.php?action=load_session&public=1&session_id='+zero['session_id'])[0]==200,'Removing a key deleted a published test'
    assert anon.request('/api.php?action=public_profile&profile_id='+pb['public_id'])[0]==200,'Historical public funding owner profile became unreachable'
    assert 'sk-or-sponsor-b-rotated' not in (storage/'sponsor-vault.json').read_text()
    # Independent processes contend on the same account and sponsor reservation.
    jobs=[subprocess.Popen(['php',str(ROOT/'tests/sponsor_race.php'),str(storage),sa['id'],str(i)],stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True) for i in range(8)]
    results=[p.communicate(timeout=30)[0].strip() for p in jobs];assert results.count('created')==2 and results.count('limited')==6,results
    for path in ['/sponsor-vault.json','/sponsor-vault.key','/lib/sponsors.php','/tests/sponsor_race.php']:assert anon.request(path)[0] in [403,404]
    # Site-key migration is explicit, idempotent, owner-bound and independent of source checkout.
    assert c.start()[0]==200 and c.init()['quota']['remaining']==2
    command=['php','-d','auto_prepend_file='+str(ROOT/'tests/mock-provider.php'),str(ROOT/'tools/migrate_site_sponsor.php'),pa['public_id']]
    env={**os.environ,'LAB_DATA_DIR':str(storage),'LAB_TEST_LEGACY_IDENTITY':'0'}
    subprocess.run(command,env=env,cwd=ROOT,check=True,capture_output=True)
    subprocess.run(command,env=env,cwd=ROOT,check=True,capture_output=True)
    site=next(r for r in a.init()['sponsors'] if r['is_site']);assert site['is_owner'] and site['profile']==pa
    assert c.start()[0]==200 and c.init()['quota']['remaining']==1,'Site-key migration reset already-consumed daily quota'
    site=save(a,None,site['label'],1,1,site);assert c.init()['quota']['remaining']==0 and c.start()[0]==400
    assert a.post('delete_sponsor',id=site['id'],confirm=site['id'])[0]==200
    assert c.start()[0]==400,'Deleted migrated site key resurrected from the legacy env fallback'
    print('PASS sponsors: encrypted custody, owner-only editing, independent quotas/races, zero-priced actors with funded jury, bound attribution, rotation/revocation and private transcript isolation.')

if __name__=='__main__':
    with tempfile.TemporaryDirectory(prefix='prisonlab-sponsors-') as tmp:
        subprocess.run(['php',str(ROOT/'tests/free_sponsor_fixture.php'),tmp],check=True)
        storage=Path(tmp)
        with (storage/'server.log').open('w') as log:
            p=subprocess.Popen(['php','-d','auto_prepend_file='+str(ROOT/'tests/mock-provider.php'),'-S','127.0.0.1:8494','router.php'],cwd=ROOT,env={**os.environ,'LAB_DATA_DIR':tmp,'LAB_TEST_LEGACY_IDENTITY':'0'},stdout=log,stderr=log,creationflags=subprocess.CREATE_NO_WINDOW if os.name=='nt' else 0)
            try:
                for _ in range(70):
                    try:
                        if urllib.request.urlopen(BASE+'/api.php?action=init_data').status==200:break
                    except OSError:time.sleep(.1)
                run(storage)
            finally:p.terminate();p.wait(timeout=10)
