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
        req=urllib.request.Request(BASE+'/api.php',data=json.dumps(dict(action=action,**data)).encode(),headers={'Content-Type':'application/json','X-CSRF-Token':self.csrf,**(headers or {})})
        try:
            with self.opener.open(req) as r:return r.status,json.load(r)
        except urllib.error.HTTPError as e:return e.code,json.load(e)
    def start(self,mode='human'):
        status,d=self.post('start_session',model='google/gemini-2.5-flash-lite',interrogator_type=mode,interrogator_model='mistralai/mistral-small-3.2-24b-instruct',case_file='char_1_victor_vance.md')
        assert status==200,d;return d['session']
    def step(self,s,n,request_id=None,headers=None):
        return self.post({'ai':'ai_vs_ai_step','human':'send_message','reverse':'reverse_answer'}[s['interrogator_type']],session_id=s['session_id'],message='ما الدليل الذي تستند إليه في إنكارك؟',expected_turns=n,request_id=request_id or f'test_request_{n:04d}',headers=headers)

def run(storage):
    a,b=Client(),Client();data=a.init();b.init()
    assert len(data['cases'])==16 and len(data['states'])==20
    assert len(data['investigators'])==10 and [p['level'] for p in data['investigators']]==list(range(1,11))
    for tier,members in data['curated'].items():
        assert len(members)<=10 and len({m['publisher'] for m in members})==len(members)
        assert all(':' not in m['id'] for m in members)
    assert all(1<=c['difficulty']['level']<=10 for c in data['cases'])
    for path in ['/.env','/logs/session_20261019_110549_3024c.json','/lib/lab.php','/tests/mock-provider.php','/assets/../.env']:
        assert a.get(path)[0]==404,path
    status,_=a.post('start_session',headers={'X-CSRF-Token':'bad'});assert status==403
    status,_=a.post('start_session',model='unknown/model',case_file='char_1_victor_vance.md');assert status==400
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
    status,d=a.post('evaluate_session',session_id=sid,headers={'X-Test-Fail-Model':'google/gemini-2.5-flash'})
    assert status==200 and d['session']['evaluation_status']=='complete',d.get('error')
    assert len(d['session']['judge_reports'])==3 and len(d['session']['judge_models'])==4
    assert d['session']['judge_progress']['google/gemini-2.5-flash']['status']=='failed'
    assert d['session']['evaluation']['quorum']['required']==3
    status,d=a.post('evaluate_session',session_id=sid);assert status==200,d;s=d['session']
    assert s['evaluation_status']=='complete' and len(s['judge_reports'])==3 and len(s['evaluation']['scores'])==9
    assert s['evaluation']['scores']['investigator_emotional'] is None
    assert s['evaluation']['investigator_coverage']==35
    assert s['evaluation']['scores']['prisoner_emotion']>=0
    calls=json.loads((storage/'test-calls.json').read_text());judge_calls=[x for x in calls if x['model']=='openai/gpt-4.1-mini'];assert len(judge_calls)==1
    count=len(calls);a.post('evaluate_session',session_id=sid);assert len(json.loads((storage/'test-calls.json').read_text()))==count
    status,d=a.post('publish_session',session_id=sid);assert status==200 and d['session']['read_only']
    status,d=b.get('/api.php?action=load_session&public=1&session_id='+sid);assert status==200 and d['session']['read_only']
    assert 'owner_id' not in d['session'] and 'processed_requests' not in d['session']
    assert len(b.get('/api.php?action=public_sessions')[1]['logs'])==1
    assert a.step(s,30)[0]==409
    partial=a.start();status,d=a.step(partial,0);assert status==200
    status,d=a.post('evaluate_session',session_id=partial['session_id'],headers={'X-Test-Fail-Judges':'1'})
    assert status==200 and d['session']['evaluation_status']=='complete' and len(d['session']['judge_reports'])==2
    assert d['session']['evaluation']['quorum']['degraded'] and d['session']['jury_wait']['elapsed_seconds']>=160
    assert sorted(d['session']['jury_wait']['attempts'].values())==[1,1,2,2]
    completed=set(d['session']['judge_reports'])
    before_calls=json.loads((storage/'test-calls.json').read_text());before_counts={mid:sum(x['model']==mid for x in before_calls) for mid in completed}
    status,d=a.post('evaluate_session',session_id=partial['session_id']);assert status==200 and d['session']['evaluation_status']=='complete'
    after_calls=json.loads((storage/'test-calls.json').read_text());assert all(sum(x['model']==mid for x in after_calls)==n for mid,n in before_counts.items())
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
            assert result['jury_wait']['elapsed_seconds']>=160
            assert max(result['jury_wait']['attempts'].values())==2
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
    assert d['session']['is_ended'] and d['session']['end_reason']=='confession'
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
        storage=Path(tmp);env={**os.environ,'LAB_DATA_DIR':tmp}
        flags=subprocess.CREATE_NO_WINDOW if os.name=='nt' else 0
        with (storage/'server.log').open('w') as log:
            proc=subprocess.Popen(['php','-d','auto_prepend_file='+str(ROOT/'tests/mock-provider.php'),'-S',f'127.0.0.1:{PORT}','router.php'],cwd=ROOT,env=env,stdout=log,stderr=log,creationflags=flags)
            try:
                for _ in range(50):
                    try:
                        if urllib.request.urlopen(BASE+'/api.php?action=init_data').status==200:break
                    except (OSError,urllib.error.URLError):time.sleep(.1)
                run(storage)
            finally:proc.terminate();proc.wait(timeout=10)
