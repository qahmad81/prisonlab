"""Sponsor monitoring thresholds, mock identity/provider only."""
import json,os,subprocess,tempfile,time,urllib.request,uuid
from pathlib import Path
from accounts import Client,ROOT,BASE

def run(storage):
    donor,visitor=Client(),Client();donor.login('donor-budget');visitor.login('visitor-budget')
    def save(old=None,**changes):
        body=dict(action='save_sponsor',label='Budget fixture',daily_limit=10,price_cap=1,active=True,**changes)
        if old:body.update(id=old['id'],revision=old['revision'])
        else:body['key']='synthetic-budget-key'
        return donor.request('/api.php',body)[:2]
    code,d=save(budget_daily_usd=0.5,budget_total_usd=4);assert code==200,d;record=d['sponsor']
    assert record['budget_daily_usd']==0.5 and record['budget_total_usd']==4
    public=visitor.init()['sponsors'];other=next(x for x in public if x['id']==record['id']);assert 'budget_daily_usd' not in other and 'budget_total_usd' not in other
    def start():return visitor.post('start_session',sponsor_id=record['id'],request_id=uuid.uuid4().hex,model='google/gemini-2.5-flash-lite',interrogator_type='human',case_file='char_1_victor_vance.md')
    code,d=start();assert code==400 and 'اليومي' in d['error'],d
    assert next(x for x in visitor.init()['sponsors'] if x['id']==record['id'])['used']==0
    code,d=save(record,budget_daily_usd=1,budget_total_usd=2.5);assert code==200,d;record=d['sponsor']
    code,d=start();assert code==400 and 'الإجمالي' in d['error'],d
    code,d=save(record,budget_daily_usd=1,budget_total_usd=4);assert code==200,d;record=d['sponsor']
    code,d=start();assert code==200,d;session=d['session']
    code,d=visitor.post('send_message',session_id=session['session_id'],request_id=uuid.uuid4().hex,expected_turns=0,message='اختبار');assert code==200,d
    code,d=save(record,budget_daily_usd=None,budget_total_usd=0);assert code==200,d;record=d['sponsor']
    before=len(json.loads((storage/'test-calls.json').read_text()));code,d=visitor.post('send_message',session_id=session['session_id'],request_id=uuid.uuid4().hex,expected_turns=1,message='اختبار ثان');assert code==400 and 'الإجمالي' in d['error'],d
    assert len(json.loads((storage/'test-calls.json').read_text()))==before
    code,d=save(record,budget_daily_usd=None,budget_total_usd=None);assert code==200,d;record=d['sponsor']
    code,d=visitor.post('send_message',session_id=session['session_id'],request_id=uuid.uuid4().hex,expected_turns=1,message='اختبار ثان');assert code==200,d
    for invalid in [-1,1000001,'nonsense']:
        code,d=save(record,budget_daily_usd=invalid);assert code==400,d
    # Missing daily reporting fails closed instead of treating unknown usage as zero.
    code,d=donor.request('/api.php',dict(action='save_sponsor',label='Missing daily',key='synthetic-missing-daily',daily_limit=2,price_cap=1,active=True,budget_daily_usd=1))[:2];assert code==200,d
    missing=d['sponsor'];code,d=visitor.post('start_session',sponsor_id=missing['id'],request_id=uuid.uuid4().hex,model='google/gemini-2.5-flash-lite',interrogator_type='human',case_file='char_1_victor_vance.md');assert code==400 and 'تعذر التحقق' in d['error'],d
    serialized=json.dumps(visitor.init(),ensure_ascii=False);assert 'synthetic-budget-key' not in serialized and 'fingerprint' not in serialized
    print('PASS sponsor budget: daily and total monitoring, preflight, saved-stage recovery, owner privacy, validation and no fallback.')

if __name__=='__main__':
    with tempfile.TemporaryDirectory(prefix='prisonlab-budget-') as tmp:
        storage=Path(tmp);subprocess.run(['php',str(ROOT/'tests/free_sponsor_fixture.php'),tmp],check=True)
        with (storage/'server.log').open('w') as log:
            proc=subprocess.Popen(['php','-d','auto_prepend_file='+str(ROOT/'tests/mock-provider.php'),'-S','127.0.0.1:8494','router.php'],cwd=ROOT,env={**os.environ,'LAB_DATA_DIR':tmp,'LAB_TEST_LEGACY_IDENTITY':'0'},stdout=log,stderr=log,creationflags=subprocess.CREATE_NO_WINDOW if os.name=='nt' else 0)
            try:
                for _ in range(70):
                    try:
                        if urllib.request.urlopen(BASE+'/api.php?action=init_data').status==200:break
                    except OSError:time.sleep(.1)
                run(storage)
            finally:proc.terminate();proc.wait(timeout=10)
