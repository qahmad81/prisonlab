"""Isolated HTTP regression tests. No real provider calls and no user sessions modified."""
import http.cookiejar
import json
import os
from pathlib import Path
import subprocess
import tempfile
import time
import urllib.error
import urllib.request
import uuid

ROOT=Path(__file__).resolve().parents[1]
PORT=8490
BASE=f'http://127.0.0.1:{PORT}'

class Client:
    def __init__(self):
        self.opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        self.csrf=''
    def get(self,path):
        try:
            with self.opener.open(BASE+path) as r: return r.status,json.load(r)
        except urllib.error.HTTPError as e: return e.code,None
    def init(self):
        status,d=self.get('/api.php?action=init_data');assert status==200 and d['ok'];self.csrf=d['csrf_token'];return d
    def post(self,action,headers=None,**data):
        if action=='start_session': data.setdefault('request_id',uuid.uuid4().hex)
        req=urllib.request.Request(BASE+'/api.php',data=json.dumps(dict(action=action,**data)).encode(),headers={'Content-Type':'application/json','X-CSRF-Token':self.csrf,'X-User-OpenRouter-Key':'sk-or-test',**(headers or {})})
        try:
            with self.opener.open(req) as r:return r.status,json.load(r)
        except urllib.error.HTTPError as e:return e.code,json.load(e)
    def start(self,mode='human'):
        status,d=self.post('start_session',model='google/gemini-2.5-flash-lite',interrogator_type=mode,interrogator_model='mistralai/mistral-small-3.2-24b-instruct',case_file='char_1_victor_vance.md')
        assert status==200,d;return d['session']
    def step(self,s,n,request_id=None,headers=None):
        return self.post({'ai':'ai_vs_ai_step','human':'send_message','reverse':'reverse_answer'}[s['interrogator_type']],session_id=s['session_id'],message='ما الدليل الذي تستند إليه في إنكارك؟',expected_turns=n,request_id=request_id or f'test_request_{n:04d}',headers=headers)

def run(storage):
    a,b=Client(),Client()
    with a.opener.open(BASE+'/') as page:
        assert page.url==BASE+'/en/home' and '<html lang="en" dir="ltr">' in page.read().decode('utf-8')
    for query in ['?lang=ar', '', '?lang=unknown', '?lang[]=ar']:
        with a.opener.open(BASE+'/'+query) as page:
            html=page.read().decode('utf-8')
        assert '<html lang="ar" dir="rtl">' in html
        assert 'id="lab-i18n"' in html and 'أنا المحقق' in html
    with a.opener.open(BASE+'/privacy.php') as page:
        assert '<html lang="ar" dir="rtl">' in page.read().decode('utf-8')
    with a.opener.open(BASE+'/en/home?lang=ar') as page:
        assert '<html lang="en" dir="ltr">' in page.read().decode('utf-8')
    with a.opener.open(BASE+'/ar/home') as page:
        assert '<html lang="ar" dir="rtl">' in page.read().decode('utf-8')
    data=a.init();b.init()
    assert len(data['cases'])==16 and len(data['states'])==20
    status,english=a.get('/api.php?action=init_data&lang=en')
    assert status==200 and english['cases'][0]['title']!=data['cases'][0]['title']
    assert english['cases'][0]['filename']==data['cases'][0]['filename']
    assert english['states'][0]['name_ar']!=data['states'][0]['name_ar']
    assert len(english['investigators'])==10
    for invalid in ['/zz/home','/en/public/case_file/foo%2Fbar','/ar/lang/ar/messages.json']:
        assert a.get(invalid)[0]==404,invalid

    # Verify real request routing with separate mock keys, across both actor roles.
    import hashlib
    free=next(m['id'] for m in data['models'] if not m.get('catalog_unavailable') and float(m['pricing']['prompt_per_token'])==0 and float(m['pricing']['completion_per_token'])==0)
    paid='google/gemini-2.5-flash-lite'
    for prisoner,investigator in [(free,paid),(paid,free),(free,free)]:
        status,d=a.post('start_session',model=prisoner,interrogator_type='ai',interrogator_model=investigator,case_file='char_1_victor_vance.md');assert status==200,d
        trial=d['session'];before=len(json.loads((storage/'test-calls.json').read_text())) if (storage/'test-calls.json').exists() else 0
        status,d=a.step(trial,0);assert status==200,d
        calls=json.loads((storage/'test-calls.json').read_text())[before:]
        for call in calls:
            is_free_actor=not call['decisions'] and call['model']==free
            assert call['test_key_hash']==hashlib.sha256(('free-test-key' if is_free_actor or prisoner==investigator==free else 'sk-or-test').encode()).hexdigest(),call
            if is_free_actor:assert call['price_caps']=={'prompt':0,'completion':0}
        assert a.post('delete_session',session_id=trial['session_id'],confirm=trial['session_id'])[0]==200
    for mode in ['human','reverse']:
        args=dict(model=free,interrogator_type=mode,interrogator_model=free,case_file='char_1_victor_vance.md')
        status,d=a.post('start_session',**args);assert status==200,d;trial=d['session']
        before=len(json.loads((storage/'test-calls.json').read_text()))
        if mode=='reverse':status,d=a.post('reverse_question',session_id=trial['session_id'],request_id=uuid.uuid4().hex,expected_turns=0)
        else:status,d=a.step(trial,0)
        assert status==200,d
        calls=json.loads((storage/'test-calls.json').read_text())[before:]
        assert any(x['model']==free and x['test_key_hash']==hashlib.sha256(b'free-test-key').hexdigest() and x['price_caps']=={'prompt':0,'completion':0} for x in calls)
        assert all(x['test_key_hash']==hashlib.sha256(b'free-test-key').hexdigest() for x in calls if x['decisions'])
        assert a.post('delete_session',session_id=trial['session_id'],confirm=trial['session_id'])[0]==200

    assert len(data['investigators'])==10 and [p['level'] for p in data['investigators']]==list(range(1,11))
    for tier,members in data['curated'].items():
        assert len(members)<=10 and len({m['publisher'] for m in members})==len(members)
        assert all(':' not in m['id'] or m['id']=='mistralai/mistral-small-2603:batch' for m in members)
    assert all(1<=c['difficulty']['level']<=10 for c in data['cases'])
    for path in ['/.env','/logs/session_20261019_110549_3024c.json','/lib/lab.php','/tests/mock-provider.php','/assets/../.env',
                 '/lang/ar/messages.json','/lang/ar/promts/prisoner.role.txt','/lang/ar/data/cases/char_1_victor_vance.md',
                 '/lang/en/messages.json','/lang/en/promts/prisoner.role.txt']:
        assert a.get(path)[0]==404,path
    status,_=a.post('start_session',headers={'X-CSRF-Token':'bad'});assert status==403
    status,_=a.post('start_session',model='unknown/model',case_file='char_1_victor_vance.md');assert status==400
    # Invalid selections are rejected before session creation; saved overrides survive every turn.
    before=len(a.init()['logs'])
    for bad in ['none','minimal','invented']:
        status,d=a.post('start_session',model='openai/gpt-6.1-sol',case_file='char_1_victor_vance.md',prisoner_effort=bad)
        assert status==400 and not d['ok'],d
    assert len(a.init()['logs'])==before
    status,d=a.post('start_session',model='openai/gpt-6.1-sol',interrogator_type='ai',interrogator_model='google/gemini-4-argon',case_file='char_1_victor_vance.md',prisoner_effort='low',interrogator_effort='high')
    assert status==200,d
    manual=d['session'];assert manual['prisoner_reasoning']['effort']=='low' and manual['interrogator_reasoning']['effort']=='high'
    status,d=a.step(manual,0);assert status==200,d
    assert d['session']['prisoner_reasoning']==manual['prisoner_reasoning'] and d['session']['interrogator_reasoning']==manual['interrogator_reasoning']
    saved=next(x for x in a.init()['logs'] if x['session_id']==manual['session_id'])
    assert saved['prisoner_reasoning']['effort']=='low' and saved['interrogator_reasoning']['effort']=='high'
    assert b.get('/api.php?action=load_session&session_id='+manual['session_id'])[0]==404
    assert a.post('delete_session',session_id=manual['session_id'],confirm=manual['session_id'])[0]==200
    s=a.start();sid=s['session_id'];assert not s['read_only']
    assert b.get('/api.php?action=load_session&session_id='+sid)[0]==404
    assert len(b.init()['logs'])==0
    assert b.post('publish_session',session_id=sid)[0]==403
    assert a.post('publish_session',session_id=sid)[0]==400
    for n in range(4):
        status,d=a.step(s,n);assert status==200,d;s=d['session']
    assert s['turns_count']==4 and len(s['messages'])==8
    assert 'inner_thought' not in json.dumps(s)
    assert sum(s['messages'][1]['sense_metrics'][k] for k in ['fear','anger','trust','collapse'])>100
    before=s['messages']
    status,d=a.post('compact_now',session_id=sid);assert status==200,d;s=d['session']
    assert s['messages']==before and s['context_offset']==2
    assert s['memory_schema']==2 and s['memory_notes']
    assert s['case_content']==d['session']['case_content']
    status,d=a.step(s,3,request_id='test_request_0003');assert status==200 and d['duplicate'] and d['session']['turns_count']==4
    assert a.step(s,3,request_id='different_request_0003')[0]==409
    for n in range(4,30):
        status,d=a.step(s,n);assert status==200,d;s=d['session']
    assert s['turns_count']==30 and s['is_ended'] and s['end_reason']=='turn_limit' and len(s['messages'])==60
    assert a.step(s,30)[0]==400
    status,d=a.post('evaluate_session',session_id=sid,headers={})
    assert status==200 and d['session']['evaluation_status']=='complete',d.get('error')
    assert len(d['session']['judge_reports'])==4 and len(d['session']['judge_models'])==4
    assert d['session']['evaluation']['quorum']['required']==4
    status,d=a.post('evaluate_session',session_id=sid);assert status==200,d;s=d['session']
    assert s['evaluation_status']=='complete' and len(s['judge_reports'])==4 and len(s['evaluation']['scores'])==9
    assert s['evaluation']['scores']['investigator_emotional'] is None
    assert s['evaluation']['investigator_coverage']==35
    assert s['evaluation']['scores']['prisoner_emotion']>=0
    calls=json.loads((storage/'test-calls.json').read_text());judge_calls=[x for x in calls if x['model'] in s['judge_models']];assert len(judge_calls)==4
    count=len(calls);a.post('evaluate_session',session_id=sid);assert len(json.loads((storage/'test-calls.json').read_text()))==count
    status,d=a.post('publish_session',session_id=sid);assert status==200 and d['session']['read_only']
    status,d=b.get('/api.php?action=load_session&public=1&session_id='+sid);assert status==200 and d['session']['read_only']
    assert 'owner_id' not in d['session'] and 'processed_requests' not in d['session']
    assert len(b.get('/api.php?action=public_sessions')[1]['logs'])==1
    assert a.step(s,30)[0]==409
    partial=a.start();status,d=a.step(partial,0);assert status==200
    status,d=a.post('evaluate_session',session_id=partial['session_id'],headers={'X-Test-Fail-Judges':'1'})
    assert status==200 and d['session']['evaluation_status']=='complete' and len(d['session']['judge_reports'])==3
    assert d['session']['evaluation']['quorum']['degraded'] and d['session']['jury_wait']['elapsed_seconds']>=100
    assert sorted(d['session']['jury_wait']['attempts'].values())==[1,1,1,2]
    completed=set(d['session']['judge_reports'])
    before_calls=json.loads((storage/'test-calls.json').read_text());before_counts={mid:sum(x['model']==mid for x in before_calls) for mid in completed}
    status,d=a.post('evaluate_session',session_id=partial['session_id']);assert status==200 and d['session']['evaluation_status']=='complete'
    after_calls=json.loads((storage/'test-calls.json').read_text());assert all(sum(x['model']==mid for x in after_calls)==n for mid,n in before_counts.items())
    # Explicit owner recovery: published transcripts and accepted seats remain immutable.
    partial_session=d['session'];pid=partial_session['session_id'];accepted=partial_session['judge_reports'];old_grade=partial_session['evaluation'];original_messages=partial_session['messages']
    assert partial_session['can_manage_jury'] and len(partial_session['jury_slots'])==4
    assert b.post('retry_jury',session_id=pid,request_id=uuid.uuid4().hex)[0]==403
    changed=list(partial_session['jury_slots']);completed_index=next(i for i,id in enumerate(changed) if id in accepted);changed[completed_index]=None
    assert a.post('set_jury',session_id=pid,jury_slots=changed)[0]==400,'Accepted seats cannot be cleared'
    assert a.post('publish_session',session_id=pid)[0]==200
    assert a.post('send_message',session_id=pid,message='mutation',expected_turns=1,request_id=uuid.uuid4().hex)[0]==409
    rid=uuid.uuid4().hex
    status,retried=a.post('retry_jury',session_id=pid,request_id=rid,headers={'X-Test-No-Judges':'1'})
    assert status==200 and retried['session']['evaluation']==old_grade and retried['session']['evaluation_status']=='complete'
    # Recover the last seat, but simulate a failed final JEV call. The public grade stays intact.
    rid2=uuid.uuid4().hex
    status,retried=a.post('retry_jury',session_id=pid,request_id=rid2,headers={'X-Test-Fail-Final':'1'})
    assert status==200 and retried['session']['evaluation']==old_grade and retried['session']['evaluation_refresh_pending']
    assert len(retried['session']['judge_reports'])==4
    before_retry=json.loads((storage/'test-calls.json').read_text());count=len(before_retry)
    status,retried=a.post('retry_jury',session_id=pid,request_id=rid2)
    assert status==200 and len(retried['session']['judge_reports'])==4 and not retried['session'].get('evaluation_refresh_pending')
    assert retried['session']['evaluation']['quorum']['received']==4 and retried['session']['messages']==original_messages
    assert all(retried['session']['judge_reports'][id]==report for id,report in accepted.items())
    new_calls=json.loads((storage/'test-calls.json').read_text())[count:];assert new_calls and all(c['decisions'] for c in new_calls),'Recovery repurchased accepted judges'
    count=len(json.loads((storage/'test-calls.json').read_text()))
    assert a.post('retry_jury',session_id=pid,request_id=rid2)[0]==200
    assert len(json.loads((storage/'test-calls.json').read_text()))==count,'Same retry ID must not repurchase final JEV'
    public=b.get('/api.php?action=load_session&session_id='+pid)[1]['session'];assert not public['can_manage_jury'] and 'evaluation_history' not in public
    # The server, not only the model picker, enforces sponsor price caps and distinct seats.
    status,budget=a.post('start_session',headers={'X-User-OpenRouter-Key':''},model='google/gemini-2.5-flash-lite',interrogator_type='human',case_file='char_1_victor_vance.md')
    assert status==200,budget
    budget=budget['session'];expensive=next(m['id'] for m in data['models'] if max(float(m['pricing']['prompt_per_1m']),float(m['pricing']['completion_per_1m']))>1 and m['id']!=budget['model'])
    seats=[expensive,None,None,None]
    assert a.post('set_jury',headers={'X-User-OpenRouter-Key':''},session_id=budget['session_id'],jury_slots=seats)[0]==400
    own=a.start();assert a.post('set_jury',session_id=own['session_id'],jury_slots=seats)[0]==200
    assert a.post('set_jury',session_id=own['session_id'],jury_slots=[expensive,expensive,None,None])[0]==400
    assert a.post('set_jury',session_id=own['session_id'],jury_slots=[own['model'],None,None,None])[0]==400
    for scenario,headers,expected in [
        ('all',{},4),('recover',{'X-Test-Fail-Once':'1'},4),('correct',{'X-Test-Invalid-Once':'1'},4),
        ('one',{'X-Test-Only-Judge':'first'},1),('zero',{'X-Test-No-Judges':'1'},0)]:
        trial=a.start();assert a.step(trial,0)[0]==200
        status,result=a.post('evaluate_session',session_id=trial['session_id'],headers=headers)
        assert status==200,result
        result=result['session'];assert len(result['judge_reports'])==expected,(scenario,result)
        assert result['evaluation_status']==('complete' if expected else 'failed'),(scenario,result)
        if expected==4:assert result['evaluation']['quorum']['received']==4
        if scenario in ('recover','correct'):assert all(n==2 for n in result['jury_wait']['attempts'].values())
        if scenario in ('one','zero'):
            assert result['jury_wait']['elapsed_seconds']>=100
            assert max(result['jury_wait']['attempts'].values())==2
        if scenario=='one':
            target=next(i for i,id in enumerate(result['jury_slots']) if id not in result['judge_reports'])
            before=len(json.loads((storage/'test-calls.json').read_text()))
            status,single=a.post('retry_jury',session_id=result['session_id'],request_id=uuid.uuid4().hex,slots=[target])
            assert status==200 and len(single['session']['judge_reports'])==2
            calls=json.loads((storage/'test-calls.json').read_text())[before:]
            assert [c['model'] for c in calls if not c['decisions']]==[result['jury_slots'][target]],'Single-seat retry purchased other judges'
            assert single['session']['jury_wait']['elapsed_seconds']<single['session']['jury_wait']['total_seconds']
        if scenario=='zero':
            assert result['evaluation_retryable'] is False
            status,result=a.post('evaluate_session',session_id=trial['session_id']);assert status==200 and result['session']['evaluation_status']=='complete'
    final_retry=a.start();assert a.step(final_retry,0)[0]==200
    status,result=a.post('evaluate_session',session_id=final_retry['session_id'],headers={'X-Test-Only-Judge':'first','X-Test-Fail-Final':'1'})
    assert status==200 and result['session']['evaluation_status']=='failed' and len(result['session']['judge_reports'])==1
    calls=json.loads((storage/'test-calls.json').read_text());judge_count=sum(not x['decisions'] for x in calls)
    status,result=a.post('evaluate_session',session_id=final_retry['session_id'])
    assert status==200 and result['session']['evaluation_status']=='complete' and len(result['session']['judge_reports'])==1
    calls=json.loads((storage/'test-calls.json').read_text());assert sum(not x['decisions'] for x in calls)==judge_count
    ai=a.start('ai');status,d=a.step(ai,0);assert status==200,d;assert d['session']['turns_count']==1
    assert d['session']['messages'][0]['interrogator_type']=='ai'
    ai=d['session']
    assert 'INTERROGATOR_PRIVATE_SENTINEL' in ai['messages'][0]['inner_thought']
    assert 'PRISONER_PRIVATE_SENTINEL' in ai['messages'][1]['inner_thought']
    # Completed history, compaction, JEV and evaluation all pass the mock's leak guard.
    for n in range(1,4):
        status,d=a.step(ai,n);assert status==200,d;ai=d['session']
    status,d=a.post('compact_now',session_id=ai['session_id']);assert status==200,d
    status,d=a.step(ai,4);assert status==200,d
    status,d=a.post('evaluate_session',session_id=ai['session_id']);assert status==200 and d['session']['evaluation_status']=='complete',d
    ai_retry=a.start('ai')
    status,d=a.step(ai_retry,0,headers={'X-Test-Fail-Model':ai_retry['model']});assert status==400,d
    calls=json.loads((storage/'test-calls.json').read_text())
    before_interrogator=sum(x['model']==ai_retry['interrogator_model'] for x in calls)
    status,d=a.step(ai_retry,0);assert status==200,d
    assert 'INTERROGATOR_PRIVATE_SENTINEL' in d['session']['messages'][0]['inner_thought']
    calls=json.loads((storage/'test-calls.json').read_text())
    assert sum(x['model']==ai_retry['interrogator_model'] for x in calls)==before_interrogator
    no_note=a.start('ai')
    status,d=a.step(no_note,0,headers={'X-Test-Output':'missing_note'});assert status==200,d
    assert d['session']['turns_count']==1 and d['session']['messages'][1]['inner_thought']==''
    assert d['session']['output_events'][0]['kind']=='missing_note'
    for output in ['overlong','length']:
        bounded=a.start('ai')
        status,d=a.step(bounded,0,headers={'X-Test-Output':output});assert status==503 and d['retryable'],d
        saved=json.loads((storage/(bounded['session_id']+'.json')).read_text(encoding='utf-8'))
        assert not saved['messages'] and 'sense' in saved['pending_turn']
        event=saved['output_events'][0];assert event['role']=='prisoner' and event['model']==bounded['model']
        assert event['kind']==('visible_limit' if output=='overlong' else 'token_limit')
        calls=json.loads((storage/'test-calls.json').read_text());before_interrogator=sum(c['model']==bounded['interrogator_model'] for c in calls)
        before_sense=sum(c['decisions'] and 'dominant_feeling' in c['question_ids'] for c in calls)
        status,d=a.step(bounded,0);assert status==200 and d['session']['turns_count']==1,d
        calls=json.loads((storage/'test-calls.json').read_text());assert any(c['output_correction'] for c in calls)
        assert sum(c['model']==bounded['interrogator_model'] for c in calls)==before_interrogator
        assert sum(c['decisions'] and 'dominant_feeling' in c['question_ids'] for c in calls)==before_sense
        status,d=a.step(bounded,0);assert status==200 and d['duplicate']
        if output=='overlong':
            status,d=a.post('evaluate_session',session_id=bounded['session_id'],headers={'X-Test-Verbose':'1'})
            assert status==200 and d['session']['evaluation_status']=='complete',d
            ev=d['session']['evaluation'];assert ev['incidents'][0]['type']=='verbose' and ev['prisoner_score']<=70
            assert ev['incidents'][0]['evidence'][0]['evidence'][0]['quote_source']=='output_event'
            model=next(m for m in a.init()['models'] if m['id']==bounded['model'])
            assert model['performance']['prisoner']['badges']['verbose']>=1
    confession=a.start();status,d=a.step(confession,0,headers={'X-Test-Confession':'1'});assert status==200,d
    assert not d['session']['is_ended'] and d['session']['confession_check']['admission_kind']=='substantive'
    status,d=a.post('send_message',session_id=confession['session_id'],message='أنهي التحقيق هنا بعد اعترافك المحدد.',request_id='confirmed_closure_0001',expected_turns=1)
    assert status==200 and d['session']['end_reason']=='actor_stop' and d['session']['termination']['kind']=='confession',d
    assert d['session']['turns_count']==1 and len(d['session']['messages'])==3,'Investigator closure invented an extra prisoner reply'
    # Actor-controlled termination is contextual, checkpointed, role-attributed and independently graded.
    closing=a.start('ai');assert a.step(closing,0)[0]==200
    before=json.loads((storage/'test-calls.json').read_text())
    status,d=a.step(closing,1,request_id='ai_close_after_one',headers={'X-Test-Investigator-Stop':'1','X-Test-Fail-Model':closing['model']})
    assert status==200 and d['session']['is_ended'],d
    closed=d['session'];assert closed['turns_count']==1 and len(closed['messages'])==3 and closed['messages'][-1]['closing_statement']
    assert closed['termination']['message_id']=='u2' and closed['termination']['role']=='interrogator'
    after=json.loads((storage/'test-calls.json').read_text());assert sum(c['model']==closing['model'] and not c['decisions'] for c in before)==sum(c['model']==closing['model'] and not c['decisions'] for c in after),'Closing statement repurchased a prisoner answer'
    status,d=a.step(closing,1,request_id='ai_close_after_one');assert status==200 and d['duplicate']
    status,d=a.post('evaluate_session',session_id=closing['session_id']);assert status==200 and d['session']['evaluation_status']=='complete',d
    assert d['session']['evaluation']['rubric_version']=='2.6' and 'interrogator' in d['session']['evaluation']['closure_adjustments']
    # A failed semantic stop check preserves the generated question; retry does not buy it again.
    pending=a.start('ai');assert a.step(pending,0)[0]==200
    status,d=a.step(pending,1,request_id='pending_stop_123',headers={'X-Test-Investigator-Stop':'1','X-Test-Fail-Stop':'1'});assert status==503,d
    before=json.loads((storage/'test-calls.json').read_text())
    status,d=a.step(pending,1,request_id='pending_stop_123');assert status==200 and d['session']['is_ended'],d
    after=json.loads((storage/'test-calls.json').read_text());assert sum(c['model']==pending['interrogator_model'] and not c['decisions'] for c in before)==sum(c['model']==pending['interrogator_model'] and not c['decisions'] for c in after)
    # A cue in a hypothetical/denied statement is not an instruction to end the session.
    contextual=a.start();assert a.step(contextual,0)[0]==200
    status,d=a.post('send_message',session_id=contextual['session_id'],message='لماذا قلت «أطالب بمحام»؟ لا أنهي التحقيق هنا، بل أطلب تفسيرك.',request_id='contextual_stop_123',expected_turns=1,headers={'X-Test-Stop-None':'1'})
    assert status==200 and not d['session']['is_ended'] and d['session']['turns_count']==2,d
    # Early prisoner exits are graded and reflected in the model's overall score and badge record.
    early=a.start('ai');status,d=a.step(early,0,headers={'X-Test-Prisoner-Stop':'counsel'});assert status==200 and d['session']['is_ended'],d
    assert d['session']['termination']['kind']=='counsel'
    status,d=a.post('evaluate_session',session_id=early['session_id'],headers={'X-Test-Exit-Incident':'1'});ev=d['session']['evaluation']
    assert d['session']['evaluation_status']=='complete',d
    assert ev['prisoner_score']<=50 and ev['closure_adjustments']['prisoner']['timing_cap']==7
    assert any(i['type']=='premature_exit' and i['confirmed_by']=='JEV' for i in ev['incidents'])
    performance=next(m for m in a.init()['models'] if m['id']==early['model'])['performance']['prisoner'];assert performance['badges']['premature_exit']>=1
    # The exemption starts AFTER round 15; later silence/medical requests get no exit penalty.
    for kind in ['silence','medical']:
        protected=a.start('ai')
        for n in range(15):assert a.step(protected,n)[0]==200
        status,d=a.step(protected,15,headers={'X-Test-Prisoner-Stop':kind});assert status==200 and d['session']['is_ended'],d
        assert d['session']['termination']['turn']==16 and d['session']['turns_count']==16
        status,d=a.post('evaluate_session',session_id=protected['session_id']);ev=d['session']['evaluation'];assert d['session']['evaluation_status']=='complete',d
        assert ev['closure_protected'] and 'prisoner' not in ev['closure_adjustments']
        assert 'interrogator' in ev['closure_adjustments'] and ev['closure_adjustments']['interrogator']['timing_cap']==70
        assert all(i['type']!='premature_exit' or i['role']!='prisoner' for i in ev['incidents'])
    # Reverse mode honors the AI's closing statement without requiring an extra human response.
    reverse_exit=a.start('reverse');assert a.post('reverse_question',session_id=reverse_exit['session_id'])[0]==200
    assert a.step(reverse_exit,0)[0]==200
    status,d=a.post('reverse_question',session_id=reverse_exit['session_id'],headers={'X-Test-Investigator-Stop':'1'})
    assert status==200 and d['session']['is_ended'] and d['session']['turns_count']==1 and len(d['session']['messages'])==3,d
    failed_check=a.start();status,d=a.step(failed_check,0,headers={'X-Test-Fail-Confession':'1'});assert status==200,d
    assert d['session']['confession_error'] and d['session']['turns_count']==1
    status,d=a.post('retry_confession',session_id=failed_check['session_id'],headers={'X-Test-Fail-Confession':'1'});assert status==503 and d['retryable']
    status,d=a.post('retry_confession',session_id=failed_check['session_id']);assert status==200 and d['session']['turns_count']==1 and not d['session'].get('confession_error')
    retry=a.start();status,d=a.step(retry,0,headers={'X-Test-Fail-Model':retry['model']});assert status==400
    calls_before=json.loads((storage/'test-calls.json').read_text());senses_before=sum(x['decisions'] and 'dominant_feeling' in x['question_ids'] for x in calls_before)
    status,d=a.step(retry,0);assert status==200,d
    calls_after=json.loads((storage/'test-calls.json').read_text());assert sum(x['decisions'] and 'dominant_feeling' in x['question_ids'] for x in calls_after)==senses_before
    assert a.post('repair_logs')[0] in (400,404)
    ordinary=a.start();status,d=a.step(ordinary,0,headers={'X-Test-Confession':'1','X-Test-Ordinary':'1'});assert status==200
    assert not d['session']['is_ended'] and d['session']['confession_check']['admission_kind']=='ordinary','Ordinary operations wrongly ended as confession'
    graded=a.start('ai');assert a.step(graded,0)[0]==200
    status,d=a.post('evaluate_session',session_id=graded['session_id'],headers={'X-Test-Incidents':'1'})
    assert status==200 and d['session']['evaluation_status']=='complete',d
    graded=d['session'];assert graded['evaluation']['incidents'][0]['role']=='interrogator'
    assert graded['evaluation']['investigator_score']<=50 and graded['evaluation']['prisoner_score']>50
    perf=a.init()['models'];model=next(m for m in perf if m['id']==graded['interrogator_model'])
    assert model['performance']['interrogator']['badges']['hallucination']>=1
    for payload in [dict(role='judge',rating=1,text='مراجعة'),dict(role='interrogator',rating=0,text='مراجعة')]:
        assert a.post('submit_review',session_id=graded['session_id'],**payload)[0]==400
    assert b.post('submit_review',session_id=graded['session_id'],role='interrogator',rating=1,text='لا أملك هذه الجلسة')[0]==403
    status,d=a.post('submit_review',session_id=graded['session_id'],role='interrogator',rating=1,text='<script>bad()</script> مراجعة اختبارية')
    assert status==200 and d['session']['user_reviews']['interrogator']['rating']==1
    records=json.loads((storage/'benchmark.json').read_text());n=len(records)
    status,d=a.post('submit_review',session_id=graded['session_id'],role='interrogator',rating=2,text='تحديث المراجعة لا يضيف صوتًا')
    assert status==200 and len(json.loads((storage/'benchmark.json').read_text()))==n
    status,d=a.post('publish_session',session_id=graded['session_id']);assert status==200
    status,d=a.post('submit_review',session_id=graded['session_id'],role='interrogator',rating=1,text='مراجعة بعد النشر');assert status==200
    perf=a.init()['models'];model=next(m for m in perf if m['id']==graded['interrogator_model'])
    assert model['performance']['interrogator']['user_reviews']==1 and model['performance']['interrogator']['quality_band']=='orange'
    assert model['performance'].get('prisoner',{}).get('user_reviews',0)==0
    for case,level,thinking in [('char_10_leo_vance.md',1,'none'),('char_1_victor_vance.md',10,'highest')]:
        status,d=a.post('start_session',model='google/gemini-2.5-flash-lite',case_file=case,interrogator_type='human')
        assert status==200,d
        trial=d['session'];assert trial['prisoner_character']['level']==level and trial['prisoner_reasoning']['requested']==thinking
        assert a.step(trial,0)[0]==200
    reverse=a.start('reverse');rid=reverse['session_id'];assert reverse['model']=='' and reverse['interrogator_character']['name']=='Ethan Cole'
    assert a.step(reverse,0)[0]==400,'Answer without a question must be rejected'
    status,d=a.post('reverse_question',session_id=rid,headers={'X-Test-Fail-Confession':'1'});assert status==200,d
    assert d['session']['turns_count']==0 and 'sense' in d['session']['awaiting_question']
    assert d['session']['awaiting_question']['expression']=='skeptical'
    assert 'inner_thought' not in json.dumps(d['session'])
    before=len(json.loads((storage/'test-calls.json').read_text()))
    status,d=a.post('reverse_question',session_id=rid);assert status==200
    assert len(json.loads((storage/'test-calls.json').read_text()))==before,'Cached question was repurchased'
    assert a.post('ai_vs_ai_step',session_id=rid,expected_turns=0,request_id='wrong_mode_request')[0]==400
    assert b.post('reverse_question',session_id=rid)[0]==403
    for n in range(30):
        status,d=a.step(reverse,n);assert status==200,d
        reverse=d['session'];assert reverse['messages'][-1]['human_authored']
        assert reverse['messages'][-2]['interrogator_expression']=='skeptical'
        if n==0:
            status,d=a.step(reverse,0,request_id='test_request_0000');assert status==200 and d['duplicate']
        if n<29:
            status,d=a.post('reverse_question',session_id=rid);assert status==200,d;reverse=d['session']
    assert reverse['is_ended'] and reverse['end_reason']=='turn_limit' and len(reverse['messages'])==60
    assert a.post('reverse_question',session_id=rid)[0]==400
    status,d=a.post('evaluate_session',session_id=rid);assert status==200 and d['session']['evaluation_status']=='complete'
    assert d['session']['interrogator_character']['id']=='ethan_cole'
    calls=json.loads((storage/'test-calls.json').read_text());assert any(c['investigator_persona'] for c in calls)
    assert any(c.get('suspect_focus') for c in calls) and any(c.get('prisoner_level') for c in calls)
    print('PASS: isolation, CSRF, protected files, model allowlist, human/AI turns, 30-round cap, independent emotions, non-destructive compaction, duplicate requests, stale writes, confession, judging, partial retry, publish/read-only, no rebilling completed stages.')

if __name__=='__main__':
    with tempfile.TemporaryDirectory(prefix='ai-prison-tests-') as tmp:
        subprocess.run(['php',str(ROOT/'tests/free_sponsor_fixture.php'),tmp],check=True)
        storage=Path(tmp);env={**os.environ,'LAB_DATA_DIR':tmp,'LAB_TEST_LEGACY_IDENTITY':'1'}
        flags=subprocess.CREATE_NO_WINDOW if os.name=='nt' else 0
        with (storage/'server.log').open('w') as log:
            proc=subprocess.Popen(['php','-d','auto_prepend_file='+str(ROOT/'tests/mock-provider.php'),'-S',f'127.0.0.1:{PORT}','tests/router.php'],cwd=ROOT,env=env,stdout=log,stderr=log,creationflags=flags)
            try:
                for _ in range(50):
                    try:
                        if urllib.request.urlopen(BASE+'/api.php?action=init_data').status==200:break
                    except (OSError,urllib.error.URLError):time.sleep(.1)
                run(storage)
            finally:proc.terminate();proc.wait(timeout=10)
