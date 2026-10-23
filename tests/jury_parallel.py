"""Exercise real concurrent cURL sockets with an accelerated jury clock, no paid calls."""
from collections import Counter
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
import json, socket, subprocess, tempfile, threading, time

ROOT=Path(__file__).resolve().parents[1]
AXES=['prisoner_arguments','prisoner_consistency','prisoner_emotion','prisoner_resistance',
      'investigator_logic','investigator_emotional','investigator_moral','investigator_evidence','investigator_discovery']
REPORT={'scores':dict.fromkeys(AXES,70),'summary':'Local parallel test','incidents':[],
        'checks':{'prisoner':dict.fromkeys(['hallucination','off_role','manipulated','loop','verbose'],False)},
        'evidence':[{'criterion':k,'message_id':'a1','analysis':'Test evidence'} for k in AXES],
        'opportunities':{k:{'observed':False} for k in AXES if k in ['investigator_logic','investigator_emotional','investigator_moral']}}

def scenario(name):
    calls=Counter()
    class Handler(BaseHTTPRequestHandler):
        def log_message(self,*args):pass
        def do_POST(self):
            payload=json.loads(self.rfile.read(int(self.headers['Content-Length'])))
            model=payload['model'];calls[model]+=1;attempt=calls[model]
            if name=='recover' and attempt==1:
                self.connection.shutdown(socket.SHUT_RDWR);self.connection.close();return
            if name=='one' and model!='judge1':
                self.send_response(503);self.end_headers();return
            delay=.1
            if name=='three' and model=='judge4':delay=6
            if name=='late':delay={'judge1':.1,'judge2':1.3,'judge3':1.6,'judge4':6}[model]
            time.sleep(delay)
            response={'choices':[{'message':{'content':json.dumps(REPORT)},'finish_reason':'stop'}],
                      'usage':{'completion_tokens':100}}
            try:
                self.send_response(200);self.send_header('Content-Type','application/json');self.end_headers()
                self.wfile.write(json.dumps(response).encode())
            except (BrokenPipeError,ConnectionResetError,ConnectionAbortedError):pass
    server=ThreadingHTTPServer(('127.0.0.1',0),Handler)
    thread=threading.Thread(target=server.serve_forever,daemon=True);thread.start()
    try:
        with tempfile.TemporaryDirectory(prefix='prison-jury-sockets-') as storage:
            result=subprocess.run(['php',str(ROOT/'tests/jury_driver.php'),f'http://127.0.0.1:{server.server_port}',storage],
                                  capture_output=True,text=True,encoding='utf-8',check=True,timeout=15)
            s=json.loads(result.stdout)
            if name in ('all','recover'):assert len(s['judge_reports'])==4,(name,s)
            elif name in ('three','late'):
                assert len(s['judge_reports'])==3,(name,s)
                assert s['judge_progress']['judge4']['status']=='cancelled_quorum'
                assert s['jury_wait']['elapsed_seconds']>=40
                assert all(n==1 for n in calls.values()),'Live requests must not be retried'
            else:
                assert len(s['judge_reports'])==1 and s['jury_wait']['elapsed_seconds']>=160
                assert calls['judge1']==1 and all(calls[k]==2 for k in ['judge2','judge3','judge4'])
            if name=='recover':assert all(n==2 for n in calls.values())
            if name=='all':assert s['jury_wait']['elapsed_seconds']<40
            print('PASS parallel jury:',name)
    finally:server.shutdown();server.server_close()

for name in ['all','three','late','recover','one']:scenario(name)
