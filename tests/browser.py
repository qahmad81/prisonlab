"""Runs UI tests against an isolated mock-provider server."""
from pathlib import Path
import os,subprocess,tempfile,time,urllib.request
root=Path(__file__).resolve().parents[1]
with tempfile.TemporaryDirectory(prefix='ai-prison-browser-') as tmp:
    with open(Path(tmp)/'server.log','w') as log:
        proc=subprocess.Popen(['php','-d','auto_prepend_file='+str(root/'tests/mock-provider.php'),'-S','127.0.0.1:8492','router.php'],cwd=root,env={**os.environ,'LAB_DATA_DIR':tmp,'LAB_TEST_LEGACY_IDENTITY':'1'},stdout=log,stderr=log,creationflags=subprocess.CREATE_NO_WINDOW if os.name=='nt' else 0)
        try:
            for _ in range(50):
                try:
                    if urllib.request.urlopen('http://127.0.0.1:8492/api.php?action=init_data').status==200:break
                except OSError:time.sleep(.1)
            subprocess.run(['node','tests/browser.cjs'],cwd=root,check=True)
        finally:proc.terminate();proc.wait(timeout=10)
