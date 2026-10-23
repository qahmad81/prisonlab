"""Standalone distribution: no external login, no daily quota, browser ownership and BYOK."""
from pathlib import Path
import http.cookiejar
import json
import os
import subprocess
import tempfile
import time
import urllib.request

import integration

ROOT = Path(__file__).resolve().parents[1]
integration.BASE = 'http://127.0.0.1:8496'


def start(client, key='', model='google/gemini-2.5-flash-lite', mode='human', investigator='minimax/minimax-m3'):
    return client.post('start_session', headers={'X-User-OpenRouter-Key': key}, model=model,
                       interrogator_type=mode, interrogator_model=investigator, case_file='char_1_victor_vance.md')


with tempfile.TemporaryDirectory(prefix='prisonlab-standalone-') as tmp:
    with open(Path(tmp) / 'server.log', 'w') as log:
        server = subprocess.Popen(['php', '-d', 'auto_prepend_file=' + str(ROOT / 'tests/mock-provider.php'),
                                   '-S', '127.0.0.1:8496', 'router.php'], cwd=ROOT,
                                  env={**os.environ, 'LAB_DATA_DIR': tmp}, stdout=log, stderr=log,
                                  creationflags=subprocess.CREATE_NO_WINDOW if os.name == 'nt' else 0)
        try:
            for _ in range(70):
                try:
                    if urllib.request.urlopen(integration.BASE + '/api.php?action=init_data').status == 200:
                        break
                except OSError:
                    time.sleep(.1)
            a, b = integration.Client(), integration.Client()
            data = a.init()
            b.init()
            assert data['edition'] == 'standalone' and data['participant_profile']['provider'] == 'local'
            assert 'quota' not in data and 'auth_ready' not in data
            assert a.get('/auth.php?action=login')[0] == 404
            assert a.get('/api.php?action=leaderboard')[0] == 405
            assert a.get('/api.php?action=public_profile')[0] == 405
            for _ in range(5):
                status, result = start(a)
                assert status == 200 and result['session']['funding_source'] == 'site', result
            sid = result['session']['session_id']
            assert b.get('/api.php?action=load_session&session_id=' + sid)[0] == 404
            assert b.post('delete_session', session_id=sid, confirm=sid)[0] == 403
            # Keep only the persistent browser ownership cookie, simulating a new PHP session.
            jar = next(h.cookiejar for h in a.opener.handlers if isinstance(h, urllib.request.HTTPCookieProcessor))
            restored = integration.Client()
            restored_jar = next(h.cookiejar for h in restored.opener.handlers if isinstance(h, urllib.request.HTTPCookieProcessor))
            for cookie in jar:
                if cookie.name == 'prisonlab_local':
                    assert cookie.has_nonstandard_attr('HttpOnly')
                    restored_jar.set_cookie(cookie)
            assert len(restored.init()['logs']) == 5
            assert restored.get('/api.php?action=load_session&session_id=' + sid)[0] == 200
            balance = a.post('key_balance', headers={'X-User-OpenRouter-Key': 'sk-or-test'})[1]['balance']
            assert balance['available_to_spend'] == 2.5 and balance['balance'] == 7.5
            assert start(a, model='openai/gpt-6.1-sol')[0] == 400
            for _ in range(5):
                assert start(a, key='sk-or-test', model='openai/gpt-6.1-sol')[0] == 200
            # Removing the key does not add a daily quota, but restores the price cap.
            assert start(a)[0] == 200
            assert start(a, model='openai/gpt-6.1-sol')[0] == 400
            for model, mode, tier, seconds in [
                ('google/gemini-2.5-flash-lite', 'human', 'economic', 50),
                ('minimax/minimax-m3', 'ai', 'medium', 80),
                ('openai/gpt-6.1-sol', 'human', 'professional', 180),
            ]:
                status, result = start(a, key='sk-or-test', model=model, mode=mode)
                assert status == 200, result
                session = result['session']
                assert session['jury_preview']['tier'] == tier and session['jury_preview']['seconds'] == seconds
                assert a.step(session, 0)[0] == 200
                status, result = a.post('evaluate_session', session_id=session['session_id'])
                assert status == 200 and result['session']['evaluation_status'] == 'complete', result
                assert len(result['session']['judge_models']) == 3
                assert result['session']['jury_wait']['total_seconds'] == seconds
                assert 'rankings' not in result['session']
            assert not (Path(tmp) / 'accounts').exists()
            print('PASS standalone: no login/config dependency, unlimited site/personal starts, persistent private ownership, key budgets, price caps, all three jury tiers and no social endpoints.')
        finally:
            server.terminate()
            server.wait(timeout=10)
