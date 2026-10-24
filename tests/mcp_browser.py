"""MCP picker/gates/wait/resume UI regression using isolated storage/mock providers."""
from pathlib import Path
import os,subprocess,tempfile,time,urllib.request
root=Path(__file__).resolve().parents[1]
with tempfile.TemporaryDirectory(prefix='prisonlab-mcp-ui-') as tmp:
    with open(Path(tmp)/'server.log','w') as log:
        proc=subprocess.Popen(['php','-d','auto_prepend_file='+str(root/'tests/mock-provider.php'),'-S','127.0.0.1:8492','router.php'],cwd=root,
            env={**os.environ,'LAB_DATA_DIR':tmp,'LAB_TEST_LEGACY_IDENTITY':'1'},stdout=log,stderr=log,creationflags=subprocess.CREATE_NO_WINDOW if os.name=='nt' else 0)
        try:
            for _ in range(70):
                try:
                    if urllib.request.urlopen('http://127.0.0.1:8492/api.php?action=init_data').status==200:break
                except OSError:time.sleep(.1)
            subprocess.run(['node','tests/mcp.cjs'],cwd=root,check=True)
        except Exception:
            print((Path(tmp)/'server.log').read_text(encoding='utf-8',errors='replace')[-3000:]);raise
        finally:proc.terminate();proc.wait(timeout=10)
