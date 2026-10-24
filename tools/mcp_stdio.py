"""Optional stdio adapter for harnesses without remote HTTP MCP support. No dependencies.

Set PRISONLAB_MCP_URL in the harness environment to the private role gate URL.
Never commit that environment or include the gate in public issue reports.
"""
import json,os,sys,urllib.request,urllib.error,urllib.parse

def main():
    # MCP lines are UTF-8, independently of a Windows terminal's legacy code page.
    sys.stdin.reconfigure(encoding='utf-8');sys.stdout.reconfigure(encoding='utf-8')
    url=os.environ.get('PRISONLAB_MCP_URL','');parts=urllib.parse.urlparse(url)
    if not (parts.scheme=='https' or (parts.scheme=='http' and parts.hostname in ('127.0.0.1','localhost','::1'))):
        print('Set PRISONLAB_MCP_URL to an HTTPS gate (or loopback HTTP for local tests).',file=sys.stderr);return 1
    # Do not follow redirects with a capability credential.
    class NoRedirect(urllib.request.HTTPRedirectHandler):
        def redirect_request(self,*args,**kwargs):return None
    client=urllib.request.build_opener(NoRedirect())
    for line in sys.stdin:
        request=None
        try:
            request=json.loads(line)
            req=urllib.request.Request(url,data=json.dumps(request,ensure_ascii=False).encode('utf-8'),headers={
                'Content-Type':'application/json','Accept':'application/json, text/event-stream'})
            try:r=client.open(req,timeout=24)
            except urllib.error.HTTPError as e:r=e
            raw=r.read(262144)
            if 'id' not in request:continue
            response=json.loads(raw) if raw else {'jsonrpc':'2.0','id':request['id'],'result':{}}
            if not isinstance(response,dict) or response.get('jsonrpc')!='2.0':raise ValueError()
        except Exception:
            if isinstance(request,dict) and 'id' not in request:continue
            response={'jsonrpc':'2.0','id':request.get('id') if isinstance(request,dict) else None,'error':{'code':-32000,'message':'PrisonLab connection failed. Check the gate, expiry and network; no turn was fabricated.'}}
        print(json.dumps(response,ensure_ascii=False),flush=True)
    return 0

if __name__=='__main__':raise SystemExit(main())
