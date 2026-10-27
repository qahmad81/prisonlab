"""Full local account UI flow with a mock OAuth issuer and isolated conversations."""
from pathlib import Path
import os, subprocess, tempfile, time, urllib.request
from accounts import Client, BASE
root=Path(__file__).resolve().parents[1]
with tempfile.TemporaryDirectory(prefix='prisonlab-community-ui-') as tmp:
    subprocess.run(['php',str(root/'tests/free_sponsor_fixture.php'),tmp],check=True)
    with open(Path(tmp)/'server.log','w') as log:
        proc=subprocess.Popen(['php','-d','auto_prepend_file='+str(root/'tests/mock-provider.php'),'-S','127.0.0.1:8494','router.php'],cwd=root,
            env={**os.environ,'LAB_DATA_DIR':tmp,'LAB_TEST_LEGACY_IDENTITY':'0'},stdout=log,stderr=log,creationflags=subprocess.CREATE_NO_WINDOW if os.name=='nt' else 0)
        try:
            for _ in range(70):
                try:
                    if urllib.request.urlopen(BASE+'/api.php?action=init_data').status==200:break
                except OSError:time.sleep(.1)
            c=Client();profile=c.login()['participant_profile'];key='sk-or-test'
            status,d=c.start(key=key,mode='ai');assert status==200;published=c.grade(d['session'],key)
            assert c.post('submit_review',session_id=published['session_id'],role='interrogator',rating=4,text='PUBLIC UI REVIEW')[0]==200
            assert c.post('publish_session',session_id=published['session_id'])[0]==200
            status,d=c.start(key=key);assert status==200;private=c.grade(d['session'],key)
            assert c.post('submit_review',session_id=private['session_id'],role='prisoner',rating=1,text='PRIVATE UI SENTINEL')[0]==200
            subprocess.run(['node','tests/community.cjs'],cwd=root,env={**os.environ,'TEST_PUBLIC_SID':published['session_id'],'TEST_PRIVATE_SID':private['session_id'],
                'TEST_PROFILE_ID':profile['public_id']},check=True)
        except Exception:
            print((Path(tmp)/'server.log').read_text(encoding='utf-8',errors='replace')[-4500:]);raise
        finally:proc.terminate();proc.wait(timeout=10)
