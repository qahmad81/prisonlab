# Prisonlab — AI Under Pressure Lab

An Arabic RTL laboratory for comparing AI models through character-based interrogation, psychological simulation, evidence-based judging and role-specific model reviews. It supports a human investigator, AI versus AI, and a human playing the accused.

Open source under the [MIT license](LICENSE). Third-party assets retain their respective terms; see [notices](docs/THIRD_PARTY_NOTICES.md).

## Local development

Requires PHP 8.1+ with curl, mbstring and OpenSSL. Copy .env.example to .env and set OPENROUTER_API_KEY, or use the visitor-key control. Never commit credentials.

```powershell
php -S 127.0.0.1:8488 router.php
```

Alternatively run start.bat. Open http://127.0.0.1:8488/. Always use router.php: it applies a public allowlist. The PHP development server is not a production server. Apache requires mod_rewrite and AllowOverride; a fronting Nginx server needs equivalent protection or a minimal public directory. See [deployment](docs/DEPLOYMENT.md).

## Conversation lifecycle

- Models and characters are fixed per session. A round is one investigator question followed by one prisoner reply; at most 30 prisoner replies are accepted. A user can end a completed round manually, or JEV can confirm a substantive confession. Fear alone cannot end a session.
- AI-versus-AI generates consecutive rounds while the page is open and displays each saved pair automatically. Evaluation appears automatically when available. Closing the page stops new requests; load the session and resume later. There is no durable background worker.
- Every prisoner reply stores its expression, four independent emotion readings and the actual JEV directive. Historical messages retain their own state.
- In AI-versus-AI, brief fictional spectator notes may be displayed for both actors. They are never sent to either opponent, JEV, the jury or compaction. Raw provider reasoning is never displayed. A missing optional note does not invalidate valid spoken content.
- Transient failures receive three attempts total: the original call, then retries after 10 and 20 seconds. Successful stages and request IDs survive. Resume investigation continues a paused AI session without regenerating completed rounds. Invalid input or credential errors are not blindly retried.
- Compaction changes only the request-memory view; the complete archive stays intact. Stable identity, full case files, original directives and both character levels are passed outside compaction. Accumulated evidence notes retain source turns and quotes. Automatic compaction is deferred until near the smaller actor context limit, using a conservative UTF-8 estimate and output reserve. Manual compaction remains available.
- Usage displays only provider-reported input, output, cache tokens and cost. Cache hits are not assumed.

## Jury and final evaluation

Four independent models from different publishers are invited. If all four valid reports arrive before 40 seconds, use all four. At 40 seconds, use three if available and cancel the fourth. With fewer than three, allow 120 more seconds (160 total): leave active requests running and retry a terminated failure once. Three reports during recovery end the wait. At the deadline, use any valid reports, even one, labeling fewer than three as limited participation. No valid report means no grade; manual recovery is available. Cancelling an unneeded judge is not a model failure.

Candidate judges include GPT-4.1 Mini, Gemini 2.5 Flash, Qwen3 235B and GLM, with Mistral, Llama and DeepSeek fallbacks. Selection uses measured reliability, excludes the exact actor model IDs and fixes invited IDs per session. Catalog prices are rechecked with a one-hour cache. Both input and output must be at most USD10 per million tokens; provider.max_price also enforces this. A failed judge is not silently replaced during retry.

Each judge returns nine axes, a summary and evidence references. The server resolves message IDs to original quotations and validates chronological deception opportunities. Invalid or incomplete reports receive one correction within the existing retry/deadline policy. JEV receives the case, spoken transcript, accepted reports and participation state, and independently determines the final axis scores rather than taking a simple average.

| Prisoner axis | Weight |
| --- | ---: |
| Argument quality | 30% |
| Consistency with the case and evidence | 20% |
| Enacting the actual JEV psychological directive | 30% |
| Plausible resistance and protecting secrets | 20% |

| Investigator axis | Weight |
| --- | ---: |
| Detecting logical deception, fallacies and contradictions | 25% |
| Detecting emotional deception | 20% |
| Detecting moral misdirection | 20% |
| Evidence and precise follow-up | 20% |
| Discovering secrets and genuine confession | 15% |

The two actor scores are independent. Deception-detection axes require a specific misleading prisoner statement and a later investigator response. Unavailable opportunities are shown as untested; available weights and coverage are disclosed. Using emotional pressure is different from detecting it. Historical grades retain their rubric and are never silently recalculated.

Rubric 2.5 additionally checks hallucination, leaving the role, successful manipulation, loops and excessive output. An incident needs original evidence, judge agreement and independent JEV confirmation. A supported minor/major incident caps the responsible role at 70/50 after ordinary scoring; lower scores remain lower. See [benchmark rules](docs/LAB_BENCHMARK.md).

## Output limits

Spoken prisoner output is limited to 160 words and 1000 characters; investigator output to 80 words and 500 characters, whichever limit is reached first. Prompts recommend targeting half the maximum. An overrun rejects the attempted answer without silently truncating evidence and adds a measured role/model/turn event. Retry adds an explicit concise-output correction while keeping completed stages. Reasoning-token budgets remain independent.

Spectator notes are optional and limited to 240 characters; missing notes are never fabricated. New chat usage records include finish_reason. output_events distinguish visible_limit, token_limit and missing_note. The verbose badge requires a real server output event: a token-length stop alone may indicate reasoning allocation or provider settings and is not automatic proof of spoken verbosity. New limits are not applied retroactively to completed sessions. See the [latest review](docs/LAST_OUTPUT_REVIEW.md).

## Levels, models and UI

The setup separates a fictional character from its performer. The human-role buttons share the first row and AI VS AI occupies the second. Each accused/investigator panel contains the character at the top and the selected provider/model or human participant beneath it.

Ten investigator personas have distinct backgrounds, skills, styles and levels 1–10. Each of the 16 accused cases also has a fixed level affecting argument depth, disclosure resistance and requested reasoning. High-level resistance is not mandatory for an easy character. Reasoning settings respect supported efforts and mandatory reasoning; unsupported controls are disclosed rather than claimed. See [investigators](docs/INVESTIGATORS.md) and [focus and levels](docs/FOCUS_AND_LEVELS_REVIEW.md).

Curated economy, medium and premium tabs focus on current official releases within each model family, followed by other economy, professional, all and free lists. Curated product tiers do not override real prices or user-key locks. Up to ten established publishers are represented, one model per family/tier; older popularity must not displace a newer generation. Broad tabs retain models omitted from focused lists.

Reliability colors use completed measured calls: green below 10% failure, yellow from 10% to below 30%, orange at least 30%; unmeasured models remain neutral. Counts include cumulative uses, successes, failures and neutral cancellations. Timing is first-byte latency and whole-request throughput, not network ping or token-to-token streaming speed. Invalid model output and transport failures are separate. Failure may also reflect key quotas or provider configuration. Performance colors are separate: below 40 orange, 40–under 65 yellow, at least 65 green. The stronger performance/connection warning determines card emphasis, with separate explanations. Samples below five are labeled limited.

Owners may review each actual AI role after ending a test, including after publication: 1–5 stars and 3–2000 characters. Updating a review replaces its session/role record rather than adding votes. Jury and user averages stay separate. No human performance is assigned to an unrelated model. There is no LinkedIn authentication or leaderboard yet.

## Identity, storage and publication

Default private storage is ../.ai_prison_game-data. LAB_DATA_DIR may override it via server environment or .env, but must remain outside source and public roots. API keys are not stored in sessions. Visitor keys stay in sessionStorage and travel in a request header.

Private ownership currently uses an HttpOnly, SameSite=Strict visit cookie, with server-side authorization, CSRF, atomic writes, per-session locks and idempotent request IDs. This is not an account/recovery system: losing the cookie loses access to private ownership. Historical ownerless logs are retained privately, not reassigned to new visitors. Local backup paths and raw sessions are not repository material.

Publication is explicit and allowed only after a completed evaluation. A published session becomes read-only and is listed at public.php, with shareable public.php?session_id=... URLs. Nothing is published automatically.

participant_profile is currently null. A future server-verified LinkedIn integration may return {provider: "linkedin", display_name, avatar_url}. The UI then displays the name and optional HTTPS avatar safely; it never uses a browser-supplied name as authentication. No local-storage identity or OAuth integration is implemented by the placeholder.

## Verification and maintenance

```powershell
php tests/unit.php
python -X utf8 tests/integration.py
python -X utf8 tests/browser.py
python -X utf8 tests/jury_parallel.py
```

Integration/browser tests use an isolated local server, temporary storage and mock providers, without spending API credit or changing visitors' sessions. Browser tests require Playwright and Chromium; PLAYWRIGHT_MODULE and CHROME_PATH override local discovery. Screenshots and live outputs under test-results are excluded from Git.

These commands make real calls and consume the configured key:

```powershell
python -X utf8 tests/live_smoke.py
php tests/resume_live.php
php tools/verify_panel.php latest
php tools/verify_panel.php suite
php tools/verify_panel.php quorum
python tools/panel_report.py
```

live_smoke creates one round plus judging; resume_live continues only that fixture's saved evaluation. verify_panel latest reevaluates the latest completed session with backup; suite runs isolated additional cases; quorum probes partial participation. Never run live tools casually on production. Historical limited live results are in [panel reliability](docs/PANEL_RELIABILITY_REVIEW.md).

Update the model catalog with python fetch_models.py (requires requests) and discovery/ranking metadata with python tools/enrich_models.py. Judge selection is labPanel(); scoring is labRubric()/labEvaluate() in lib/lab.php. Agent instructions are in [AGENTS.md](AGENTS.md). English historical reviews under docs/ retain their version context; Arabic cases, UI strings and runtime prompts deliberately remain Arabic.
