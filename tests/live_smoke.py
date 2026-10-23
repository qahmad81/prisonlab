"""Explicitly run one real round and one panel with the configured key; never publishes."""
import json
import os
from pathlib import Path
import subprocess
import tempfile
import time
import urllib.request
import integration as harness

root=Path(__file__).resolve().parents[1]
harness.BASE='http://127.0.0.1:8491'
with tempfile.TemporaryDirectory(prefix='ai-prison-live-smoke-') as tmp:
    with open(Path(tmp)/'server.log','w') as log:
        proc=subprocess.Popen(['php','-S','127.0.0.1:8491','router.php'],cwd=root,env={**os.environ,'LAB_DATA_DIR':tmp},stdout=log,stderr=log,creationflags=subprocess.CREATE_NO_WINDOW if os.name=='nt' else 0)
        try:
            for _ in range(50):
                try:
                    if urllib.request.urlopen(harness.BASE+'/api.php?action=init_data').status==200:break
                except OSError:time.sleep(.1)
            a=harness.Client();a.init();s=a.start()
            print('Starting one live prisoner round with independent JEV emotion scales...',flush=True)
            for attempt in range(3):
                if attempt:
                    delay=attempt*10;print(f'Retrying live round after {delay}s...',flush=True);time.sleep(delay)
                status,d=a.step(s,0)
                if d.get('ok') or not d.get('retryable'):break
            if not d.get('ok'):raise RuntimeError(d.get('error','round failed'))
            s=d['session'];print('Live round saved. JEV metrics: '+json.dumps(s['messages'][1]['sense_metrics']),flush=True)
            print('Starting live three-judge panel and final JEV assessment...',flush=True)
            for attempt in range(3):
                if attempt:
                    delay=attempt*10;print(f'Retrying incomplete live evaluation after {delay}s...',flush=True);time.sleep(delay)
                status,d=a.post('evaluate_session',session_id=s['session_id']);s=d['session']
                if s['evaluation_status']=='complete' or not s.get('evaluation_retryable'):break
            out=root/'test-results';out.mkdir(exist_ok=True)
            (out/'live-smoke.json').write_text(json.dumps(s,ensure_ascii=False,indent=2),encoding='utf-8')
            if s['evaluation_status']!='complete':
                private=json.loads((Path(tmp)/(s['session_id']+'.json')).read_text(encoding='utf-8'))
                (out/'live-invalid-reports.json').write_text(json.dumps(private.get('judge_invalid_reports',{}),ensure_ascii=False,indent=2),encoding='utf-8')
            if s['evaluation_status']!='complete':raise RuntimeError(s.get('evaluation_error','evaluation failed'))
            print('PASS live: '+json.dumps({'judge_models':s['evaluation']['judge_models'],'prisoner':s['evaluation']['prisoner_score'],'investigator':s['evaluation']['investigator_score'],'usage_calls':len(s['usage'])}),flush=True)
        finally:proc.terminate();proc.wait(timeout=10)
